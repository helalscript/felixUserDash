<?php

namespace App\Http\Controllers\API\V1\Admin\Coupon;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Services\API\V1\Admin\Coupon\CouponService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CouponController extends Controller
{
    public function __construct(protected CouponService $couponService)
    {
        //
    }

    /**
     * Display a listing of coupons.
     */
    public function index(Request $request)
    {
        try {
            $coupons = $this->couponService->index($request);

            return Helper::jsonResponse(true, 'Coupons fetched successfully', 200, $coupons, true);
        } catch (Exception $e) {
            Log::error('Admin\\CouponController::index'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created coupon.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:coupons,code',
            'type' => 'required|in:percentage,amount',
            'value' => 'required|numeric|min:0',
            'min_order_value' => 'required|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1|max:10000',
            'is_unlimited_usage' => 'nullable|boolean',
            'is_unlimited_time' => 'required|boolean',
            'start_date' => 'required_if:is_unlimited_time,false|nullable|date',
            'start_time' => 'required_if:is_unlimited_time,false|nullable|date_format:H:i',
            'end_date' => 'required_if:is_unlimited_time,false|nullable|date|after_or_equal:start_date',
            'end_time' => 'required_if:is_unlimited_time,false|nullable|date_format:H:i',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id',
        ]);
        // Additional validation for percentage type
        if ($validatedData['type'] === 'percentage' && $validatedData['value'] > 100) {
            return Helper::jsonErrorResponse('Percentage value cannot exceed 100%', 422);
        }

        try {
            $coupon = $this->couponService->store($validatedData);

            return Helper::jsonResponse(true, 'Coupon created successfully', 200, $coupon);
        } catch (Exception $e) {
            Log::error('Admin\\CouponController::store'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Display the specified coupon.
     */
    public function show(string $id)
    {
        try {
            $coupon = $this->couponService->show($id);

            return Helper::jsonResponse(true, 'Coupon fetched successfully', 200, $coupon);
        } catch (Exception $e) {
            Log::error('Admin\\CouponController::show'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Update the specified coupon.
     */
    public function update(Request $request, string $id)
    {
        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|nullable|string|max:50|unique:coupons,code,'.$id,
            'type' => 'sometimes|required|in:percentage,amount',
            'value' => 'sometimes|required|numeric|min:0',
            'min_order_value' => 'sometimes|required|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1|max:10',
            'is_unlimited_usage' => 'nullable|boolean',
            'is_unlimited_time' => 'sometimes|required|boolean',
            'start_date' => 'required_if:is_unlimited_time,false|nullable|date',
            'start_time' => 'required_if:is_unlimited_time,false|nullable|date_format:H:i',
            'end_date' => 'required_if:is_unlimited_time,false|nullable|date|after_or_equal:start_date',
            'end_time' => 'required_if:is_unlimited_time,false|nullable|date_format:H:i',
            'status' => 'sometimes|in:active,scheduled,expired,deactivated,exhausted',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        // Additional validation for percentage type
        if (isset($validatedData['type']) && $validatedData['type'] === 'percentage' && isset($validatedData['value']) && $validatedData['value'] > 100) {
            return Helper::jsonErrorResponse('Percentage value cannot exceed 100%', 422);
        }

        try {
            $coupon = $this->couponService->update($id, $validatedData);

            return Helper::jsonResponse(true, 'Coupon updated successfully', 200, $coupon);
        } catch (Exception $e) {
            Log::error('Admin\\CouponController::update'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Remove (deactivate) the specified coupon.
     */
    public function destroy(string $id)
    {
        try {
            $coupon = $this->couponService->destroy($id);

            return Helper::jsonResponse(true, 'Coupon deactivated successfully', 200, $coupon);
        } catch (Exception $e) {
            Log::error('Admin\\CouponController::destroy'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }
}

