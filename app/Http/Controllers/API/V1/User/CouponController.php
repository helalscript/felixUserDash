<?php

namespace App\Http\Controllers\API\V1\User;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CouponController extends Controller
{
    /**
     * Validate and apply a coupon code.
     */
    public function validate(Request $request)
    {
        $validatedData = $request->validate([
            'code' => 'required|string',
            'order_amount' => 'required|numeric|min:0',
        ]);

        try {
            $coupon = Coupon::where('code', $validatedData['code'])->first();

            if (!$coupon) {
                return Helper::jsonErrorResponse('Invalid coupon code', 404);
            }

            // Update coupon status
            $coupon->updateStatus();
            $coupon->refresh();
            // Check if coupon is valid
            if (!$coupon->isValid()) {
                $message = 'Coupon is not valid.';
                if ($coupon->status === 'expired') {
                    $message = 'This coupon has expired.';
                } elseif ($coupon->status === 'exhausted') {
                    $message = 'This coupon has reached its usage limit.';
                } elseif ($coupon->status === 'deactivated') {
                    $message = 'This coupon has been deactivated.';
                } elseif ($coupon->status === 'scheduled') {
                    $message = 'This coupon is not yet active.';
                }
                return Helper::jsonErrorResponse($message, 422);
            }

            // Check minimum order value
            if ($validatedData['order_amount'] < $coupon->min_order_value) {
                return Helper::jsonErrorResponse(
                    'Minimum order value of $' . number_format($coupon->min_order_value, 2) . ' required for this coupon.',
                    422
                );
            }

            // Calculate discount
            $originalAmount = $validatedData['order_amount'];
            $discountAmount = $coupon->calculateDiscount($originalAmount);
            $finalAmount = $originalAmount - $discountAmount;

            // Get time left
            $timeLeft = $coupon->time_left;

            return Helper::jsonResponse(true, 'Coupon applied successfully', 200, [
                'coupon' => [
                    'id' => $coupon->id,
                    'code' => $coupon->code,
                    'name' => $coupon->name,
                    'type' => $coupon->type,
                    'value' => $coupon->value,
                ],
                'original_price' => number_format($originalAmount, 2),
                'discount_amount' => number_format($discountAmount, 2),
                'final_amount' => number_format($finalAmount, 2),
                'time_left' => $timeLeft,
                'expires_at' => $coupon->is_unlimited_time ? null : ($coupon->end_date && $coupon->end_time 
                    ? Carbon::parse($coupon->end_date->format('Y-m-d') . ' ' . $coupon->end_time, 'Asia/Kuala_Lumpur')->toDateTimeString()
                    : null),
            ]);
        } catch (Exception $e) {
            Log::error('User\\CouponController::validate'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }
}

