<?php

namespace App\Http\Controllers\API\V1\User;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\User\OrderResource;
use App\Services\API\V1\User\OrderService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function __construct(protected OrderService $orderService)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $orders = $this->orderService->index($request);

            // return Helper::jsonResponse(true, 'Orders fetched successfully', 200, $orders, true);
            return Helper::jsonResponse(true, 'Orders fetched successfully', 200, OrderResource::collection($orders), true);
        } catch (Exception $e) {
            Log::error('OrderController::index'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'package_id' => 'required_without:addon_id|exists:packages,id',
            'addon_id' => 'required_without:package_id|exists:addons,id',
            
            'business_name' => 'required|string|max:255',
            'business_industry' => 'nullable|string|max:255',
            'business_requirement_description' => 'nullable|string|max:10000',
            'voice_preference' => 'required|in:male,female,other',
            'attachment' => 'nullable|array',
            'attachment.*' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,gif,svg,webp|max:20480',
        ]);
        try {
            $order = $this->orderService->store($validatedData);
 
            return Helper::jsonResponse(true, 'Order created successfully', 200, new OrderResource($order));
        } catch (Exception $e) {
            Log::error('OrderController::store'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $order = $this->orderService->show($id);

            return Helper::jsonResponse(true, 'Order fetched successfully', 200, new OrderResource($order));
        } catch (Exception $e) {
            Log::error('OrderController::show'.$e->getMessage());

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
            $this->orderService->destroy($id);

            return Helper::jsonResponse(true, 'Order deleted successfully', 200);
        } catch (Exception $e) {
            Log::error('OrderController::destroy'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }
}
