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
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PayPalService
{
    protected PayPalClient $provider;

    public function __construct()
    {
        $this->provider = new PayPalClient;
        $this->provider->setApiCredentials(config('paypal'));
        $this->provider->getAccessToken();
    }

    /**
     * Create PayPal payment and return approval URL.
     *
     * @param array $validatedData
     * @param string $orderType
     * @param $order
     * @return string
     * @throws Exception
     */
    public function createPayment(array $validatedData, string $orderType, $order): string
    {
        try {
            $amountValue = (string) number_format((float) $validatedData['amount'], 2, '.', '');

            $customData = json_encode([
                'user_id' => $validatedData['user_id'] ?? auth()->id(),
                'order_id' => isset($validatedData['order_id']) ? $validatedData['order_id'] : $validatedData['order_addon_id'],
                'order_type' => $orderType,
            ]);

            $data = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'amount' => [
                            'currency_code' => 'USD',
                            'value' => $amountValue,
                        ],
                        'description' => 'Order Type: ' . $orderType,
                        'custom_id' => $customData,
                    ],
                ],
                'application_context' => [
                    'return_url' => $validatedData['success_url'],
                    'cancel_url' => $validatedData['cancel_url'],
                ],
            ];

            $response = $this->provider->createOrder($data);

            if (isset($response['status']) && in_array($response['status'], ['CREATED', 'created'])) {
                foreach ($response['links'] ?? [] as $link) {
                    if (in_array($link['rel'] ?? '', ['approve', 'approval_url'])) {
                        return $link['href'];
                    }
                }
            }

            throw new Exception('Failed to create PayPal payment: ' . ($response['message'] ?? json_encode($response)));
        } catch (Exception $e) {
            Log::error('PayPalService::createPayment: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Execute PayPal payment after user approval.
     *
     * @param string $paymentId
     * @param string $payerId
     * @return array
     * @throws Exception
     */
    public function executePayment(string $orderId, ?string $payerId = null): array
    {
        try {
            $this->provider->setApiCredentials(config('paypal'));
            $this->provider->getAccessToken();

            $response = $this->provider->capturePaymentOrder($orderId);

            return $response;
        } catch (Exception $e) {
            Log::error('PayPalService::executePayment: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle PayPal webhook.
     *
     * @param \Illuminate\Http\Request $request
     * @return bool
     */
    public function handleWebhook($request)
    {
        try {
            $body = $request->getContent();
            $event = json_decode($body, true);
            Log::info('Paypal webhook event: ' . json_encode($event));        
            if (!$event) {
                Log::error('PayPalService::handleWebhook Invalid JSON');
                return false;
            }

            $eventType = $event['event_type'] ?? null;

            switch ($eventType) {
                case 'CHECKOUT.ORDER.APPROVED':
                    Log::info('PayPalService::handleWebhook Order approved (Orders v2): ' . ($event['resource']['id'] ?? 'unknown'));
                    $this->handleOrderApproved($event);
                    return true;

                // case 'PAYMENT.SALE.COMPLETED':
                // case 'PAYMENT.CAPTURE.COMPLETED':
                //     Log::info('PayPalService::handleWebhook Payment completed: ' . ($event['resource']['id'] ?? 'unknown'));
                //     $this->handlePaymentSuccess($event);
                //     return true;

                case 'PAYMENT.SALE.DENIED':
                case 'PAYMENT.CAPTURE.DENIED':
                case 'PAYMENT.CAPTURE.CANCELLED':
                    Log::info('PayPalService::handleWebhook Payment denied: ' . ($event['resource']['id'] ?? 'unknown'));
                    $this->handlePaymentFailure($event);
                    return true;

                default:
                    Log::info('PayPalService::handleWebhook Unhandled event type: ' . $eventType);
                    return false;
            }
        } catch (Exception $e) {
            Log::error('PayPalService::handleWebhook: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle CHECKOUT.ORDER.APPROVED - Orders v2, user approved on PayPal.
     */
    protected function handleOrderApproved($event): void
    {
        DB::beginTransaction();

        try {
            $resource = $event['resource'] ?? [];
            $orderId = $resource['id'] ?? null;

            if (!$orderId) {
                throw new Exception('Order ID missing in webhook');
            }

            if (Payment::where('intent_id', $orderId)->exists()) {
                Log::info('PayPal order already processed: ' . $orderId);
                DB::rollBack();
                return;
            }

            $this->executePayment($orderId);

            $purchaseUnits = $resource['purchase_units'] ?? [];
            $unit = $purchaseUnits[0] ?? null;

            if (!$unit) {
                throw new Exception('Purchase unit missing in webhook');
            }

            $customData = $unit['custom_id'] ?? null;
            $metadata = $customData ? json_decode($customData, true) : [];
            $orderIdLocal = $metadata['order_id'] ?? null;
            $orderType = $metadata['order_type'] ?? 'order';
            $userId = $metadata['user_id'] ?? null;

            if (!$orderIdLocal || !$userId) {
                throw new Exception('Order ID or User ID missing in metadata');
            }

            $amountData = $unit['amount'] ?? [];
            $paymentAmount = $amountData['value'] ?? null;

            $modelClass = $orderType === 'addon' ? OrderAddon::class : Order::class;
            $order = $modelClass::find((int) $orderIdLocal);

            if (!$order) {
                throw new Exception('Order not found: ' . $orderIdLocal);
            }

            if (!$paymentAmount) {
                $paymentAmount = $order->amount;
            }

            Payment::create([
                'intent_id' => $orderId,
                'user_id' => (int) $userId,
                'paymentable_id' => (int) $orderIdLocal,
                'paymentable_type' => $modelClass,
                'paid_at' => now(),
                'amount' => $paymentAmount,
                'currency' => $amountData['currency_code'] ?? 'USD',
                'status' => 'paid',
            ]);

            $order->update(['payment_status' => 'paid']);

            // Record coupon usage when payment completes (Order or OrderAddon with coupon)
            if ($order->coupon_id) {
                CouponUsage::create([
                    'coupon_id' => $order->coupon_id,
                    'user_id' => (int) $userId,
                    'order_id' => $orderType === 'order' ? (int) $orderIdLocal : null,
                    'order_addon_id' => $orderType === 'addon' ? (int) $orderIdLocal : null,
                    'used_at' => now(),
                ]);
                $order->coupon?->updateStatus();
            }

            $this->createInvoice((int) $orderIdLocal, $orderType, $paymentAmount, 'paid');

            DB::commit();
            Log::info('PayPalService::handleOrderApproved Order captured and recorded: ' . $orderId);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('PayPalService::handleOrderApproved: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle successful payment (PAYMENT.SALE.COMPLETED / PAYMENT.CAPTURE.COMPLETED).
     */
    protected function handlePaymentSuccess($event): void
    {
        DB::beginTransaction();

        try {
            $resource = $event['resource'] ?? [];
            $paymentId = $resource['id'] ?? null;

            if (!$paymentId) {
                throw new Exception('Payment ID missing in webhook');
            }

            if (Payment::where('intent_id', $paymentId)->exists()) {
                Log::info('PayPal payment already processed: ' . $paymentId);
                DB::rollBack();
                return;
            }

            $transactions = $resource['transactions'] ?? [];
            $customData = !empty($transactions) && isset($transactions[0]['custom']) ? $transactions[0]['custom'] : null;
            $metadata = $customData ? json_decode($customData, true) : [];
            $orderId = $metadata['order_id'] ?? null;
            $orderType = $metadata['order_type'] ?? 'order';
            $userId = $metadata['user_id'] ?? null;

            if (!$orderId || !$userId) {
                throw new Exception('Order ID or User ID missing in metadata. Payment ID: ' . $paymentId);
            }

            $modelClass = $orderType === 'addon' ? OrderAddon::class : Order::class;
            $order = $modelClass::find((int) $orderId);

            if (!$order) {
                throw new Exception('Order not found');
            }

            $amountData = $resource['amount'] ?? $transactions[0]['amount'] ?? [];
            $paymentAmount = $amountData['total'] ?? $order->amount;

            Payment::create([
                'intent_id' => $paymentId,
                'user_id' => (int) $userId,
                'paymentable_id' => (int) $orderId,
                'paymentable_type' => $modelClass,
                'paid_at' => now(),
                'amount' => $paymentAmount,
                'currency' => $amountData['currency'] ?? 'USD',
                'status' => 'paid',
            ]);

            $order->update(['payment_status' => 'paid']);

            $this->createInvoice((int) $orderId, $orderType, $paymentAmount, 'paid');

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('PayPalService::handlePaymentSuccess: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle failed payment.
     */
    protected function handlePaymentFailure($event): void
    {
        try {
            $resource = $event['resource'] ?? [];
            $paymentId = $resource['id'] ?? null;
            Log::info('PayPalService::handlePaymentFailure Payment failed: ' . $paymentId);
        } catch (Exception $e) {
            Log::error('PayPalService::handlePaymentFailure: ' . $e->getMessage());
        }
    }

    /**
     * Create invoice for order.
     */
    protected function createInvoice($orderId, $orderType, $amount, $status = 'paid')
    {
        try {
            $modelClass = $orderType === 'addon' ? OrderAddon::class : Order::class;
            $order = $modelClass::find($orderId);

            if (!$order) {
                Log::error('PayPalService::createInvoice Order not found, ID: ' . $orderId);
                return null;
            }

            if (Invoice::where('invoiceable_id', $order->id)->where('invoiceable_type', $modelClass)->first()) {
                return null;
            }

            return Invoice::create([
                'invoiceable_id' => $order->id,
                'invoiceable_type' => $modelClass,
                'invoice_number' => $this->generateInvoiceNumber(),
                'issued_at' => now(),
                'amount' => $amount,
                'status' => $status,
            ]);
        } catch (Exception $e) {
            Log::error('PayPalService::createInvoice: ' . $e->getMessage());
            throw $e;
        }
    }

    private function generateInvoiceNumber(): string
    {
        return 'INV-' . now()->format('Ymd') . '-' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    }
}
