<?php

namespace App\Services\API\V1\User;

use App\Models\Order;
use App\Models\OrderAddon;
use App\Models\Payment;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(
        protected StripeService $stripeService,
        protected PayPalService $paypalService
    ) {
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

            $perPage = $request->per_page ?? 100;
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
     * Create a payment intent via Stripe.
     * 
     * @param array $validatedData
     * @return string
     * @throws Exception
     */
    public function createPaymentIntent(array $validatedData)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                throw new Exception('User not authenticated');
            }
            $order = null;
            // Validate order addon exists or order exists and get amount
            if (isset($validatedData['order_addon_id'])) {
                // Get order amount and validate
                $order = $this->getAmount($validatedData['order_addon_id'], 'addon', $user->id);
                $amount = $order->amount;
            } else {
                // Validate order exists
                if (!isset($validatedData['order_id'])) {
                    throw new Exception('Order ID is required');
                }
                // Get order amount and validate
                $order = $this->getAmount(isset($validatedData['order_id']) ? $validatedData['order_id'] : null, 'order', $user->id);
                $amount = $order->amount;
            }

            $validatedData['amount'] = $amount;
            $validatedData['user_id'] = $user->id;


            // Check payment method and create payment accordingly
            $orderType = isset($validatedData['order_addon_id']) ? 'addon' : 'order';
            
            if ($validatedData['payment_method'] === 'stripe') {
                // Create payment intent via Stripe
                $paymentUrl = $this->stripeService->stripePaymentUrl($validatedData, $orderType, $order);
                return $paymentUrl;
            } elseif ($validatedData['payment_method'] === 'paypal') {
                // Create PayPal payment
                $paymentUrl = $this->paypalService->createPayment($validatedData, $orderType, $order);
                return $paymentUrl;
            } else {
                throw new Exception('Payment method not supported');
            }
        } catch (Exception $e) {
            Log::error('PaymentService::createPaymentIntent: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle payment webhook (Stripe or PayPal)
     * Note: No user authentication here as webhook comes from payment provider
     */
    public function handleWebhook($request, $provider = 'stripe')
    {
        try {
            if ($provider === 'stripe') {
                $result = $this->stripeService->stripeHandleWebhook($request);
            } elseif ($provider === 'paypal') {
                $result = $this->paypalService->handleWebhook($request);
            } else {
                throw new Exception('Invalid payment provider');
            }

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
     */
    private function getAmount(int $orderId, string $type, int $userId)
    {
        try {
            // Fetch order based on type
            if ($type === 'addon') {
                $order = OrderAddon::where('user_id', $userId)
                    ->with(['addon', 'order', 'payments'])
                    ->find($orderId);
            } else {
                $order = Order::where('user_id', $userId)
                    ->with(['orderable', 'payments'])
                    ->find($orderId);
            }

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
            
            return $order;
        } catch (Exception $e) {
            Log::error('PaymentService::getAmount: ' . $e->getMessage());
            throw $e;
        }
    }
}