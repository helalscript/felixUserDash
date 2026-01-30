<?php

namespace App\Services\API\V1\User;

use App\Models\CouponUsage;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderAddon;
use App\Models\Payment;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeService
{
    protected $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret_key'));
    }

    // /**
    //  * Create a Stripe payment intent.
    //  *
    //  * @param  array  $validatedData
    //  * @param  string  $orderType
    //  * @return string  Client secret of the payment intent
    //  * @throws Exception
    //  */
    // public function stripePaymentIntent(array $validatedData, string $orderType)
    // {
    //     try {
    //         $paymentIntent = $this->stripe->paymentIntents->create([
    //             'amount' => intval($validatedData['amount'] * 100),
    //             'currency' => 'usd',
    //             'automatic_payment_methods' => [
    //                 'enabled' => true,
    //             ],
    //             'metadata' => [
    //                 'user_id' => $validatedData['user_id'] ?? auth()->id(),
    //                 'order_id' => $validatedData['order_id'],
    //                 'order_type' => $orderType,
    //             ],
    //         ]);

    //         return $paymentIntent->client_secret;
    //     } catch (ApiErrorException $e) {
    //         Log::error('StripeService::stripePaymentIntent Stripe API error: ' . $e->getMessage());
    //         throw new Exception('Stripe API error: ' . $e->getMessage());
    //     } catch (Exception $e) {
    //         Log::error('StripeService::stripePaymentIntent: ' . $e->getMessage());
    //         throw $e;
    //     }
    // }

    /**
     * Handle Stripe webhook.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public function stripeHandleWebhook($request)
    {
        try {
            Log::info('StripeService::stripeHandleWebhook Event 21: ');

            $payload = $request->getContent();
            $sigHeader = $request->header('Stripe-Signature');
            $endpointSecret = config('services.stripe.webhook_secret');
            // Verify webhook signature
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
            Log::info('StripeService::stripeHandleWebhook Event 22: ');
            // Handle different event types
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    Log::info('StripeService::stripeHandleWebhook Payment intent succeeded: '.$event->data->object->id);
                    $this->handlePaymentSuccess($event->data->object);

                    return true;
                case 'checkout.session.completed':
                    Log::info('StripeService::stripeHandleWebhook Checkout session completed: '.$event->data->object->id);
                    $this->handleCheckoutSessionCompleted($event->data->object);

                    return true;
                case 'payment_intent.payment_failed':
                    Log::info('StripeService::stripeHandleWebhook Payment intent failed: '.$event->data->object->id);
                    $this->handlePaymentFailure($event->data->object);

                    return true;

                default:
                    Log::info('StripeService::stripeHandleWebhook Unhandled event type: '.$event->type);

                    return false;
            }
        } catch (SignatureVerificationException $e) {
            Log::error('StripeService::stripeHandleWebhook Signature verification failed: '.$e->getMessage());
            throw new Exception('Invalid webhook signature');
        } catch (ApiErrorException $e) {
            Log::error('StripeService::stripeHandleWebhook Stripe API error: '.$e->getMessage());
            throw new Exception('Stripe API error: '.$e->getMessage());
        } catch (Exception $e) {
            Log::error('StripeService::stripeHandleWebhook: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle payment success.
     *
     * @param  \Stripe\PaymentIntent  $paymentIntent
     */
    protected function handlePaymentSuccess($paymentIntent): void
    {
        try {
            DB::beginTransaction();

            // Check if payment already recorded (prevent duplicates)
            $existingPayment = Payment::where('intent_id', $paymentIntent->id)->first();
            if ($existingPayment) {
                Log::info('Payment already recorded: '.$paymentIntent->id);
                DB::rollBack();

                return;
            }

            // Record successful payment
            Payment::create([
                'intent_id' => $paymentIntent->id,
                'user_id' => $paymentIntent->metadata->user_id,
                'paymentable_id' => $paymentIntent->metadata->order_id,
                'paymentable_type' => $paymentIntent->metadata->order_type === 'addon' ? OrderAddon::class : Order::class,
                'paid_at' => now(),
                'amount' => $paymentIntent->amount / 100,
                'currency' => $paymentIntent->currency,
                'status' => 'paid',
            ]);

            // Update order status
            $order = Order::find($paymentIntent->metadata->order_id);
            if ($order) {
                $order->update(['payment_status' => 'paid']);
            }

            // Create invoice
            $this->createInvoice(
                $paymentIntent->metadata->order_id,
                $paymentIntent->metadata->order_type,
                $paymentIntent->amount / 100,
                'paid'
            );

            DB::commit();
            Log::info('Payment successful: '.$paymentIntent->id);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('StripeService::handlePaymentSuccess: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle payment failure.
     *
     * @param  \Stripe\PaymentIntent  $paymentIntent
     */
    protected function handlePaymentFailure($paymentIntent): void
    {
        try {
            DB::beginTransaction();

            // Check if payment already recorded
            $existingPayment = Payment::where('intent_id', $paymentIntent->id)->first();
            if ($existingPayment) {
                Log::info('Failed payment already recorded: '.$paymentIntent->id);
                DB::rollBack();

                return;
            }

            // Record failed payment
            Payment::create([
                'intent_id' => $paymentIntent->id,
                'user_id' => $paymentIntent->metadata->user_id,
                'paymentable_id' => $paymentIntent->metadata->order_id,
                'paymentable_type' => $paymentIntent->metadata->order_type === 'order' ? Order::class : OrderAddon::class,
                'amount' => $paymentIntent->amount / 100,
                'currency' => $paymentIntent->currency,
                'status' => 'failed',
                'failure_reason' => $paymentIntent->last_payment_error->message ?? 'Unknown error',
            ]);

            // Update order status
            $order = Order::find($paymentIntent->metadata->order_id);
            if ($order) {
                $order->update(['payment_status' => 'failed']);
            }

            DB::commit();
            Log::info('Payment failed: '.$paymentIntent->id);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('StripeService::handlePaymentFailure: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle checkout.session.completed event
     *
     * @param  \Stripe\Checkout\Session  $session
     *
     * @throws \Exception
     */
    protected function handleCheckoutSessionCompleted($session): void
    {
        DB::beginTransaction();

        try {
            // ✅ Metadata directly from session
            $orderType = $session->metadata->order_type ?? null;
            $orderId = $session->metadata->order_id ?? null;
            $userId = $session->metadata->user_id ?? null;

            if (! $orderType || ! $orderId || ! $userId) {
                throw new Exception('Required metadata missing');
            }

            // PaymentIntent ID
            $paymentIntentId = $session->payment_intent;

            if (! $paymentIntentId) {
                throw new Exception('PaymentIntent ID missing');
            }

            // Prevent duplicate webhook
            if (Payment::where('intent_id', $paymentIntentId)->exists()) {
                Log::info('Checkout already processed: '.$paymentIntentId);
                DB::rollBack();

                return;
            }

            // Resolve model
            $modelClass = $orderType === 'addon'
                ? OrderAddon::class
                : Order::class;

            $order = $modelClass::find($orderId);
            if (! $order) {
                throw new Exception('Order not found');
            }

            // Save payment
            Payment::create([
                'intent_id' => $paymentIntentId,
                'user_id' => $userId,
                'paymentable_id' => $orderId,
                'paymentable_type' => $modelClass,
                'paid_at' => now(),
                'amount' => $session->amount_total / 100,
                'currency' => $session->currency,
                'status' => 'paid',
            ]);

            // Update order
            $order->update([
                'payment_status' => 'paid',
            ]);

            // Record coupon usage when payment completes (only for Order type with coupon)
            if ($orderType === 'order' && $order instanceof Order && $order->coupon_id) {
                CouponUsage::create([
                    'coupon_id' => $order->coupon_id,
                    'user_id' => $userId,
                    'order_id' => $orderId,
                    'used_at' => now(),
                ]);
                $order->coupon?->updateStatus();
            }

            // Create invoice
            $this->createInvoice(
                $orderId,
                $orderType,
                $session->amount_total / 100,
                'paid'
            );

            DB::commit();

            Log::info('Checkout session processed', [
                'order_id' => $orderId,
                'order_type' => $orderType,
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('handleCheckoutSessionCompleted failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Create an invoice for an order.
     *
     * @param  int  $orderId
     * @param  float  $amount
     * @param  string  $status
     * @param  string  $orderType
     * @return \App\Models\Invoice
     */
    public function createInvoice($orderId, $orderType, $amount, $status = 'paid')
    {
        try {
            if ($orderType === 'addon') {
                $order = OrderAddon::find($orderId);
                if (! $order) {
                    Log::error('StripeService::createInvoice Order addon not found, ID: '.$orderId);
                    // throw new Exception('Order addon not found');
                }
            } else {
                $order = Order::find($orderId);
                if (! $order) {
                    Log::error('StripeService::createInvoice Order not found, ID: '.$orderId);
                    // throw new Exception('Order not found');
                }
            }

            // Check if invoice already exists
            $existingInvoice = Invoice::where('invoiceable_id', $order->id)
                ->where('invoiceable_type', $orderType === 'addon' ? OrderAddon::class : Order::class)
                ->first();
            if ($existingInvoice) {
                Log::info('Invoice already exists for order: '.$orderId);

                return $existingInvoice;
            }

            $invoice = Invoice::create([
                'invoiceable_id' => $order->id,
                'invoiceable_type' => $orderType === 'addon' ? OrderAddon::class : Order::class,
                'invoice_number' => $this->generateInvoiceNumber(),
                'issued_at' => now(),
                'amount' => $amount,
                'status' => $status,
            ]);

            return $invoice;
        } catch (Exception $e) {
            Log::error('StripeService::createInvoice: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate unique invoice number
     */
    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $date = now()->format('Ymd');
        $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

        return "{$prefix}-{$date}-{$random}";
    }

    public function stripePaymentUrl(array $validatedData, string $orderType, $order)
    {
        try {
            $session = $this->stripe->checkout->sessions->create([
                'payment_method_types' => ['card','link'],

                'mode' => 'payment',

                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => 'Order Payment',
                            'description' => 'Order Type: '.$orderType,
                        ],
                        'unit_amount' => intval($validatedData['amount'] * 100),
                    ],
                    'quantity' => 1,
                ]],

                'metadata' => [
                    'user_id' => $validatedData['user_id'] ?? auth()->id(),
                    'order_id' => isset($validatedData['order_id']) ? $validatedData['order_id'] : $validatedData['order_addon_id'],
                    'order_type' => $orderType,
                ],
                // if success ans cancel url
                'success_url' => $validatedData['success_url'],
                'cancel_url' => $validatedData['cancel_url'],
            ]);

            // THIS IS THE PAYMENT URL
            return $session->url;

        } catch (ApiErrorException $e) {
            Log::error('Stripe Checkout error: '.$e->getMessage());
            throw new Exception('Stripe error: '.$e->getMessage());
        }
    }
}
