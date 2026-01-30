<?php

namespace App\Http\Controllers\API\V1\User;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Services\API\V1\User\OrderAddonService;
use Exception;
use Illuminate\Http\Request;
use Log;

class OrderAddonController extends Controller
{
    public function __construct(protected OrderAddonService $orderAddonService)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $orderAddons = $this->orderAddonService->index($request);
            return Helper::jsonResponse(true, 'Order addons fetched successfully', 200, $orderAddons,true);
        } catch (Exception $e) {
            Log::error('OrderAddonController::index'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'addon_id' => 'required|exists:addons,id',
            'coupon_code' => 'nullable|string',
        ]);
        try {
            $orderAddon = $this->orderAddonService->store($validated);

            return Helper::jsonResponse(true, 'Order addon created successfully', 200, $orderAddon);
        } catch (Exception $e) {
            Log::error('OrderAddonController::store'.$e->getMessage());
            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $orderAddon = $this->orderAddonService->show($id);

            return Helper::jsonResponse(true, 'Order addon fetched successfully', 200, $orderAddon);
        } catch (Exception $e) {
            Log::error('OrderAddonController::show'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $orderAddon = $this->orderAddonService->destroy($id);

            return Helper::jsonResponse(true, 'Order addon deleted successfully', 200, $orderAddon);
        } catch (Exception $e) {
            Log::error('OrderAddonController::destroy'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }
}
