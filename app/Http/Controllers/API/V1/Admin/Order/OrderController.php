<?php

namespace App\Http\Controllers\API\V1\Admin\Order;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Services\API\V1\Admin\Order\OrderService;
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

            return Helper::jsonResponse(true, 'Orders fetched successfully', 200, $orders, true);
        } catch (Exception $e) {
            Log::error('OrderController::index'.$e->getMessage());

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

            return Helper::jsonResponse(true, 'Order fetched successfully', 200, $order);
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
        $validatedData = $request->validate([
            'status' => 'required|in:pending,processing,completed,cancelled',
        ]);
        try {
            $order = $this->orderService->update($id, $request->all());

            return Helper::jsonResponse(true, 'Order updated successfully', 200, $order);
        } catch (Exception $e) {
            Log::error('OrderController::update'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $order = $this->orderService->destroy($id);

            return Helper::jsonResponse(true, 'Order deleted successfully', 200, $order);
        } catch (Exception $e) {
            Log::error('OrderController::destroy'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    // update order status
    public function updateStatus(Request $request, string $id)
    {
        $validatedData = $request->validate([
            'status' => 'required|in:pending,in_progress,completed,cancelled',
        ]);
        try {
            $order = $this->orderService->updateStatus($id, $validatedData);

            return Helper::jsonResponse(true, 'Order status updated successfully', 200, $order);
        } catch (Exception $e) {
            Log::error('OrderController::updateStatus'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    // file upload
    public function fileUpload(Request $request, string $id)
    {
        $validatedData = $request->validate([
            'attachment' => 'required|array',
            'attachment.*' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,gif,svg,webp,mp4,mov,avi,wmv,flv,mkv,webm,mp3,wav,ogg,m4a,aac,flac,opus,mka,m4b,m4p,m4v,m4a,aac,flac,opus,mka,m4b,m4p,m4v|max:212000', // 200MB
        ]);
        try {
            $order = $this->orderService->fileUpload($id, $validatedData);

            return Helper::jsonResponse(true, 'Order delivery file uploaded successfully', 200, $order);
        } catch (Exception $e) {
            Log::error('OrderController::fileUpload'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }
}
