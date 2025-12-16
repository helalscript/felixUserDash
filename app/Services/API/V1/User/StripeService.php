<?php

namespace App\Services\API\V1\User;

use App\Models\Invoice;
use App\Models\Order;
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

    /**
     * Create a payment intent.
     *
     * @return string
     */
    public function stripePaymentIntent(array $validatedData)
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => intval($validatedData['amount'] * 100),
                'currency' => 'usd',
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
                'metadata' => [
                    'user_id' => $validatedData['user_id'] ?? auth()->id(),
                    'order_id' => $validatedData['order_id'],
                ],
            ]);

            return $paymentIntent->client_secret;
        } catch (ApiErrorException $e) {
            Log::error('StripeService::stripePaymentIntent Stripe API error: '.$e->getMessage());
            throw new Exception('Stripe API error: '.$e->getMessage());
        } catch (Exception $e) {
            Log::error('StripeService::stripePaymentIntent: '.$e->getMessage());
            throw $e;
        }
    }

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

            $payload        = $request->getContent();
            $sigHeader      = $request->header('Stripe-Signature');
            $endpointSecret = config('services.stripe.webhook_secret');
            // Verify webhook signature
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
            Log::info('StripeService::stripeHandleWebhook Event 22: ');
            // Handle different event types
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $this->handlePaymentSuccess($event->data->object);

                    return true;

                case 'payment_intent.payment_failed':
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
            $payment = Payment::create([
                'intent_id' => $paymentIntent->id,
                'user_id' => $paymentIntent->metadata->user_id,
                'paymentable_id' => $paymentIntent->metadata->order_id,
                'paymentable_type' => Order::class,
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
            $payment = Payment::create([
                'intent_id' => $paymentIntent->id,
                'user_id' => $paymentIntent->metadata->user_id,
                'paymentable_id' => $paymentIntent->metadata->order_id,
                'paymentable_type' => Order::class,
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
     * Create an invoice for an order.
     *
     * @param  int  $orderId
     * @param  float  $amount
     * @param  string  $status
     * @return \App\Models\Invoice
     */
    public function createInvoice($orderId, $amount, $status = 'paid')
    {
        try {
            $order = Order::find($orderId);
            if (! $order) {
                Log::error('StripeService::createInvoice Order not found, ID: '.$orderId);
                throw new Exception('Order not found');
            }

            // Check if invoice already exists
            $existingInvoice = Invoice::where('order_id', $orderId)->first();
            if ($existingInvoice) {
                Log::info('Invoice already exists for order: '.$orderId);

                return $existingInvoice;
            }

            $invoice = Invoice::create([
                'order_id' => $order->id,
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
}
