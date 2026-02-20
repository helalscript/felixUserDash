<?php

namespace App\Http\Controllers\API\V1\Public;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\Coupon;
use App\Models\CouponCheckoutLink;
use App\Models\Package;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckoutLinkController extends Controller
{
    /**
     * Validate checkout link params and return package/addon + coupon info for frontend.
     * Public route - no auth required. Frontend uses this to pre-fill checkout.
     */
    public function validate(Request $request)
    {
        $request->validate([
            'coupon' => 'required|string',
            'user' => 'required|integer',
            'package' => 'required_without:addon|nullable|integer',
            'addon' => 'required_without:package|nullable|integer',
        ], [
            'package.required_without' => 'Either package or addon is required.',
            'addon.required_without' => 'Either package or addon is required.',
        ]);

        try {
            $couponCode = $request->coupon;
            $userId = (int) $request->user;
            $packageId = $request->filled('package') ? (int) $request->package : null;
            $addonId = $request->filled('addon') ? (int) $request->addon : null;

            if (! $packageId && ! $addonId) {
                return Helper::jsonErrorResponse('Invalid checkout link.', 404);
            }

            $coupon = Coupon::where('code', $couponCode)->first();
            if (! $coupon) {
                return Helper::jsonErrorResponse('Invalid checkout link.', 404);
            }

            $linkQuery = CouponCheckoutLink::where('coupon_id', $coupon->id)->where('user_id', $userId);
            if ($packageId) {
                $linkQuery->where('package_id', $packageId)->whereNull('addon_id');
            } else {
                $linkQuery->where('addon_id', $addonId)->whereNull('package_id');
            }
            $link = $linkQuery->first();

            if (! $link) {
                return Helper::jsonErrorResponse('Invalid or expired checkout link.', 404);
            }

            $user = User::find($userId);
            if (! $user) {
                return Helper::jsonErrorResponse('Invalid checkout link.', 404);
            }

            $coupon->updateStatus();
            $coupon->refresh();

            if (! $coupon->isValid()) {
                return Helper::jsonErrorResponse('This coupon is no longer valid (expired or exhausted).', 422);
            }

            $package = null;
            $addon = null;
            $originalAmount = 0;

            if ($packageId) {
                $package = Package::find($packageId);
                if (! $package) {
                    return Helper::jsonErrorResponse('Invalid checkout link.', 404);
                }
                $originalAmount = (float) $package->price;
            } else {
                $addon = Addon::find($addonId);
                if (! $addon) {
                    return Helper::jsonErrorResponse('Invalid checkout link.', 404);
                }
                $originalAmount = (float) $addon->price;
            }

            if ($originalAmount < (float) $coupon->min_order_value) {
                return Helper::jsonErrorResponse('Product does not meet minimum order value.', 422);
            }

            $discountAmount = $coupon->calculateDiscount($originalAmount);
            $finalAmount = $originalAmount - $discountAmount;

            $response = [
                'valid' => true,
                'checkout_link' => $link->checkout_link,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'package' => $package ? [
                    'id' => $package->id,
                    'title' => $package->title,
                    'subtitle' => $package->subtitle,
                    'price' => $package->price,
                ] : null,
                'addon' => $addon ? [
                    'id' => $addon->id,
                    'title' => $addon->title,
                    'sub_title' => $addon->sub_title,
                    'price' => $addon->price,
                ] : null,
                'coupon' => [
                    'id' => $coupon->id,
                    'code' => $coupon->code,
                    'name' => $coupon->name,
                    'type' => $coupon->type,
                    'value' => $coupon->value,
                ],
                'original_amount' => $originalAmount,
                'discount_amount' => $discountAmount,
                'final_amount' => $finalAmount,
            ];

            return Helper::jsonResponse(true, 'Checkout link is valid', 200, $response);
        } catch (Exception $e) {
            Log::error('CheckoutLinkController::validate'.$e->getMessage());

            return Helper::jsonErrorResponse('Unable to validate checkout link.', 500);
        }
    }
}
