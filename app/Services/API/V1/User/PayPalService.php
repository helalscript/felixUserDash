<?php

namespace App\Services\API\V1\User;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderAddon;
use App\Models\Payment;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PayPal\Api\Amount;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\Payment as PayPalPayment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;

class PayPalService
{
    protected $apiContext;

    public function __construct()
    {
        $this->apiContext = new ApiContext(
            new OAuthTokenCredential(
                config('services.paypal.client_id'),
                config('services.paypal.client_secret')
            )
        );

        $this->apiContext->setConfig([
            'mode' => config('services.paypal.mode', 'sandbox'),
            'http.ConnectionTimeOut' => 30,
            'log.LogEnabled' => true,
            'log.FileName' => storage_path('logs/paypal.log'),
            'log.LogLevel' => 'INFO',
        ]);
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

            $payer = new Payer();
            $payer->setPaymentMethod('paypal');

            $item = new Item();
            $item->setName('Order Payment')
                ->setCurrency('USD')
                ->setQuantity(1)
                ->setPrice($amountValue);

            $itemList = new ItemList();
            $itemList->setItems([$item]);

            $amount = new Amount();
            $amount->setCurrency('USD')
                ->setTotal($amountValue);

            $transaction = new Transaction();
            $transaction->setAmount($amount)
                ->setItemList($itemList)
                ->setDescription('Order Type: ' . $orderType)
                ->setInvoiceNumber(uniqid())
                ->setCustom(
                    json_encode([
                        'user_id' => $validatedData['user_id'] ?? auth()->id(),
                        'order_id' => isset($validatedData['order_id']) ? $validatedData['order_id'] : $validatedData['order_addon_id'],
                        'order_type' => $orderType,
                    ])
                );

            $redirectUrls = new RedirectUrls();
            $redirectUrls->setReturnUrl($validatedData['success_url'])
                ->setCancelUrl($validatedData['cancel_url']);

            $payment = new PayPalPayment();
            $payment->setIntent('sale')
                ->setPayer($payer)
                ->setRedirectUrls($redirectUrls)
                ->setTransactions([$transaction]);

            $payment->create($this->apiContext);

            // Get approval URL
            $approvalUrl = $payment->getApprovalLink();

            return $approvalUrl;
        } catch (\PayPal\Exception\PayPalConnectionException $e) {
            Log::error('PayPalService::createPayment PayPal API error: ' . $e->getMessage());
            Log::error('PayPalService::createPayment PayPal API error data: ' . $e->getData());
            throw new Exception('PayPal API error: ' . $e->getMessage());
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
     * @return PayPalPayment
     * @throws Exception
     */
    public function executePayment(string $paymentId, string $payerId): PayPalPayment
    {
        try {
            $payment = PayPalPayment::get($paymentId, $this->apiContext);

            $execution = new PaymentExecution();
            $execution->setPayerId($payerId);

            $payment->execute($execution, $this->apiContext);

            return $payment;
        } catch (\PayPal\Exception\PayPalConnectionException $e) {
            Log::error('PayPalService::executePayment PayPal API error: ' . $e->getMessage());
            Log::error('PayPalService::executePayment PayPal API error data: ' . $e->getData());
            throw new Exception('PayPal API error: ' . $e->getMessage());
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

            if (!$event) {
                Log::error('PayPalService::handleWebhook Invalid JSON');
                return false;
            }

            $eventType = $event['event_type'] ?? null;

            switch ($eventType) {
                case 'PAYMENTS.PAYMENT.CREATED':
                    // User approved payment on PayPal - execute and capture the payment
                    Log::info('PayPalService::handleWebhook Payment created (user approved): ' . ($event['resource']['id'] ?? 'unknown'));
                    $this->handlePaymentCreated($event);
                    return true;

                case 'PAYMENT.SALE.COMPLETED':
                case 'PAYMENT.CAPTURE.COMPLETED':
                    Log::info('PayPalService::handleWebhook Payment completed: ' . ($event['resource']['id'] ?? 'unknown'));
                    $this->handlePaymentSuccess($event);
                    return true;

                case 'PAYMENT.SALE.DENIED':
                case 'PAYMENT.CAPTURE.DENIED':
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
     * Handle PAYMENTS.PAYMENT.CREATED - User approved payment on PayPal.
     * Execute the payment and record it.
     */
    protected function handlePaymentCreated($event): void
    {
        DB::beginTransaction();

        try {
            $resource = $event['resource'] ?? [];
            $paymentId = $resource['id'] ?? null;

            if (!$paymentId) {
                throw new Exception('Payment ID missing in webhook');
            }

            // Check if payment already recorded (prevent duplicate processing)
            if (Payment::where('intent_id', $paymentId)->exists()) {
                Log::info('PayPal payment already processed: ' . $paymentId);
                DB::rollBack();
                return;
            }

            // Get payer_id from resource
            $payerId = $resource['payer']['payer_info']['payer_id'] ?? null;

            if (!$payerId) {
                throw new Exception('Payer ID missing in webhook');
            }

            // Execute the payment to capture funds
            $executedPayment = $this->executePayment($paymentId, $payerId);

            // Get transaction data from resource (before execution, structure is same)
            $transactions = $resource['transactions'] ?? [];
            $transaction = $transactions[0] ?? null;

            if (!$transaction) {
                throw new Exception('Transaction data missing in webhook');
            }

            // Parse custom data
            $customData = $transaction['custom'] ?? null;
            $metadata = $customData ? json_decode($customData, true) : [];
            $orderId = $metadata['order_id'] ?? null;
            $orderType = $metadata['order_type'] ?? 'order';
            $userId = $metadata['user_id'] ?? null;

            if (!$orderId || !$userId) {
                throw new Exception('Order ID or User ID missing in metadata. Payment ID: ' . $paymentId);
            }

            // Get amount from transaction
            $amountData = $transaction['amount'] ?? [];
            $paymentAmount = $amountData['total'] ?? null;

            // Resolve model
            $modelClass = $orderType === 'addon' ? OrderAddon::class : Order::class;
            $order = $modelClass::find((int) $orderId);

            if (!$order) {
                throw new Exception('Order not found: ' . $orderId);
            }

            if (!$paymentAmount) {
                $paymentAmount = $order->amount;
            }

            // Save payment
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

            // Update order
            $order->update([
                'payment_status' => 'paid',
            ]);

            // Create invoice
            $this->createInvoice((int) $orderId, $orderType, $paymentAmount, 'paid');

            DB::commit();
            Log::info('PayPalService::handlePaymentCreated Payment executed and recorded: ' . $paymentId);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('PayPalService::handlePaymentCreated: ' . $e->getMessage());
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
            $amount = $resource['amount'] ?? null;
            $customData = $resource['custom'] ?? null;

            if (!$paymentId) {
                throw new Exception('Payment ID missing in webhook');
            }

            // Check if payment already recorded
            if (Payment::where('intent_id', $paymentId)->exists()) {
                Log::info('PayPal payment already processed: ' . $paymentId);
                DB::rollBack();
                return;
            }

            // Parse custom data from transaction
            $transactions = $resource['transactions'] ?? [];
            $customData = null;
            if (!empty($transactions) && isset($transactions[0]['custom'])) {
                $customData = $transactions[0]['custom'];
            }
            
            $metadata = $customData ? json_decode($customData, true) : [];
            $orderId = $metadata['order_id'] ?? null;
            $orderType = $metadata['order_type'] ?? 'order';
            $userId = $metadata['user_id'] ?? null;

            // If custom data not found, try to get from related_resources
            if (!$orderId || !$userId) {
                // Try to get from sale/transaction details
                $relatedResources = $transactions[0]['related_resources'] ?? [];
                if (!empty($relatedResources)) {
                    // Log for debugging
                    Log::info('PayPalService::handlePaymentSuccess - Custom data not found, checking related resources');
                }
                
                // If still not found, we might need to store payment ID temporarily
                // For now, throw exception
                if (!$orderId || !$userId) {
                    throw new Exception('Order ID or User ID missing in metadata. Payment ID: ' . $paymentId);
                }
            }

            // Resolve model
            $modelClass = $orderType === 'addon' ? OrderAddon::class : Order::class;
            $order = $modelClass::find($orderId);

            if (!$order) {
                throw new Exception('Order not found');
            }

            // Get amount
            $paymentAmount = $amount['total'] ?? $order->amount;

            // Save payment
            Payment::create([
                'intent_id' => $paymentId,
                'user_id' => $userId,
                'paymentable_id' => $orderId,
                'paymentable_type' => $modelClass,
                'paid_at' => now(),
                'amount' => $paymentAmount,
                'currency' => $amount['currency'] ?? 'USD',
                'status' => 'paid',
            ]);

            // Update order
            $order->update([
                'payment_status' => 'paid',
            ]);

            // Create invoice
            $this->createInvoice($orderId, $orderType, $paymentAmount, 'paid');

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

            // You can update order status or send notifications here
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
                return;
            }

            // Check if invoice already exists
            $existingInvoice = Invoice::where('invoiceable_id', $order->id)
                ->where('invoiceable_type', $modelClass)
                ->first();

            if ($existingInvoice) {
                Log::info('PayPalService::createInvoice Invoice already exists for order: ' . $orderId);
                return $existingInvoice;
            }

            $invoice = Invoice::create([
                'invoiceable_id' => $order->id,
                'invoiceable_type' => $modelClass,
                'invoice_number' => $this->generateInvoiceNumber(),
                'issued_at' => now(),
                'amount' => $amount,
                'status' => $status,
            ]);

            return $invoice;
        } catch (Exception $e) {
            Log::error('PayPalService::createInvoice: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate unique invoice number.
     */
    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $date = now()->format('Ymd');
        $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

        return "{$prefix}-{$date}-{$random}";
    }
}

