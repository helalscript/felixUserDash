<?php

namespace App\Services\API\V1\User;

use App\Models\Order;
use App\Models\Payment;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(protected StripeService $stripeService)
    {
        //
    }

    /**
     * Fetch all payments for authenticated user
     */
    public function index($request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                throw new Exception('User not authenticated');
            }

            $perPage = $request->per_page ?? 10;
            $payments = Payment::where('user_id', $user->id)
                ->with(['paymentable'])
                ->latest()
                ->paginate($perPage);

            return $payments;
        } catch (Exception $e) {
            Log::error('PaymentService::index: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Display a specific payment
     */
    public function show(int $id)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                throw new Exception('User not authenticated');
            }

            $payment = Payment::where('user_id', $user->id)
                ->with(['paymentable'])
                ->find($id);

            if (!$payment) {
                throw new Exception('Payment not found');
            }

            return $payment;
        } catch (Exception $e) {
            Log::error('PaymentService::show: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create payment intent
     */
    public function createPaymentIntent(array $validatedData)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                throw new Exception('User not authenticated');
            }

            // Validate order exists
            if (!isset($validatedData['order_id'])) {
                throw new Exception('Order ID is required');
            }

            // Get order amount and validate
            $amount = $this->getAmount($validatedData['order_id'], $user->id);
            $validatedData['amount'] = $amount;
            $validatedData['user_id'] = $user->id;

            // Check if payment method is supported
            if ($validatedData['payment_method'] !== 'stripe') {
                throw new Exception('Payment method not supported');
            }

            // Create payment intent via Stripe
            $clientSecret = $this->stripeService->stripePaymentIntent($validatedData);

            return $clientSecret;
        } catch (Exception $e) {
            Log::error('PaymentService::createPaymentIntent: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle Stripe webhook
     * Note: No user authentication here as webhook comes from Stripe
     */
    public function handleWebhook($request)
    {
        try {
            $result = $this->stripeService->stripeHandleWebhook($request);
            
            return $result;
        } catch (Exception $e) {
            Log::error('PaymentService::handleWebhook: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get the amount of the order and validate
     *
     * @param int $orderId
     * @param int $userId
     * @return float
     */
    private function getAmount(int $orderId, int $userId): float
    {
        try {
            $order = Order::where('user_id', $userId)
                ->with(['orderable', 'payments'])
                ->find($orderId);

            // Check if order exists
            if (!$order) {
                throw new Exception('Order not found');
            }

            // Check if order is already paid (only check 'paid' status)
            $paidPayments = $order->payments()
                ->where('status', 'paid')
                ->exists();

            if ($paidPayments) {
                throw new Exception('Order already paid');
            }

            // Check if order has valid amount
            if (!$order->amount || $order->amount <= 0) {
                throw new Exception('Invalid order amount');
            }

            return (float) $order->amount;
        } catch (Exception $e) {
            Log::error('PaymentService::getAmount: ' . $e->getMessage());
            throw $e;
        }
    }
}