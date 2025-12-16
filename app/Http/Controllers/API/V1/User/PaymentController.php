<?php

namespace App\Http\Controllers\API\V1\User;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Services\API\V1\User\PaymentService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(protected PaymentService $paymentService)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function getAllPayment(Request $request)
    {
        try {
            $payments = $this->paymentService->index($request);

            return Helper::jsonResponse(true,'Payments retrieved successfully',200,$payments, true);
        } catch (Exception $e) {
            Log::error('PaymentController::getAllPayment: '.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function getSinglePayment(string $id)
    {
        try {
            $payment = $this->paymentService->show($id);

            return Helper::jsonResponse(true,'Payment retrieved successfully',200,$payment);
        } catch (Exception $e) {
            Log::error('PaymentController::getSinglePayment: '.$e->getMessage());

            // Check if it's a not found error
            $statusCode = str_contains($e->getMessage(), 'not found') ? 404 : 500;

            return Helper::jsonErrorResponse($e->getMessage(), $statusCode);
        }
    }

    /**
     * Create payment intent for an order
     */
    public function createPaymentIntent(Request $request)
    {
        $validatedData = $request->validate([
            'payment_method' => 'required|in:stripe', //in future we will add other payment methods like paypal, bank transfer, etc.
            'order_id' => 'required|integer|exists:orders,id',
        ]);

        try {
            $clientSecret = $this->paymentService->createPaymentIntent($validatedData);

            return Helper::jsonResponse(true,'Payment intent created successfully',200,['client_secret' => $clientSecret]);
        } catch (Exception $e) {
            Log::error('PaymentController::createPaymentIntent: '.$e->getMessage());

            // Handle specific errors
            $statusCode = 500;
            $message = $e->getMessage();

            if (str_contains($message, 'not found')) {
                $statusCode = 404;
            } elseif (str_contains($message, 'already paid')) {
                $statusCode = 400;
            } elseif (str_contains($message, 'not authenticated')) {
                $statusCode = 401;
            }

            return Helper::jsonErrorResponse($message, $statusCode);
        }
    }

    /**
     * Handle Stripe webhook events
     * Note: This endpoint should be excluded from CSRF and auth middleware
     */
    public function handleWebhook(Request $request)
    {
        try {
            $result = $this->paymentService->handleWebhook($request);

            // Always return 200 to Stripe
            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully',
            ], 200);

        } catch (Exception $e) {
            Log::error('PaymentController::handleWebhook: '.$e->getMessage(), [
                'request_content' => $request->getContent(),
                'headers' => $request->headers->all(),
            ]);

            // Still return 200 to prevent Stripe from retrying
            // But log the error for debugging
            return response()->json([
                'success' => false,
                'message' => 'Webhook received but processing failed',
                'error' => $e->getMessage(),
            ], 200);
        }
    }
}
