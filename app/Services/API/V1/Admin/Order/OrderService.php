<?php

namespace App\Services\API\V1\Admin\Order;

use App\Models\Order;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class OrderService
{
    protected $user;

    public function __construct()
    {
        $this->user = Auth::user();
    }

    /**
     * Fetch all resources.
     *
     * @return mixed
     */
    public function index($request)
    {
        try {

            $perPage = $request->per_page ?? 25;
            $search = $request->search ?? null;
            $dateFrom = $request->date_from ?? null;
            $dateTo = $request->date_to ?? null;

            $query = Order::query()
                ->with('orderable', 'detail', 'user:id,name,email,phone,avatar', 'invoices', 'payments')
                ->when($search, function ($query) use ($search) {
                    $query->where('order_number', 'like', '%'.$search.'%')
                        ->orWhereHas('orderable', function ($orderableQuery) use ($search) {
                            $orderableQuery->where('title', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('detail', function ($detailQuery) use ($search) {
                            $detailQuery->where('name', 'like', '%'.$search.'%')
                                ->orWhere('industry', 'like', '%'.$search.'%')
                                ->orWhere('requirement', 'like', '%'.$search.'%')
                                ->orWhere('voice_preference', 'like', '%'.$search.'%');
                        });
                })
                ->when($dateFrom, function ($query) use ($dateFrom) {
                    $query->where('start_date', '>=', $dateFrom);
                })
                ->when($dateTo, function ($query) use ($dateTo) {
                    $query->where('start_date', '<=', $dateTo);
                })
                ->orderBy('id', 'desc');

            return $query->paginate($perPage);
        } catch (Exception $e) {
            Log::error('OrderService::index'.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Display a specific resource.
     *
     * @return mixed
     */
    public function show(int $id)
    {
        try {
            $order = Order::with(['orderable', 'detail', 'user:id,name,email,phone,avatar', 'invoices', 'payments'])->find($id);
            if (! $order) {
                throw new Exception('Order not found');
            }

            return $order;

        } catch (Exception $e) {
            Log::error('OrderService::show'.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete a specific resource.
     *
     * @return mixed
     */
    public function destroy(int $id)
    {
        try {
            $order = Order::find($id);
            if (! $order) {
                throw new Exception('Order not found');
            }
            $order->delete();

            return $order;

        } catch (Exception $e) {
            Log::error('OrderService::destroy'.$e->getMessage());
            throw $e;
        }
    }

    public function updateStatus(int $id, array $validatedData)
    {
        try {
            $order = Order::find($id);
            if (! $order) {
                throw new Exception('Order not found');
            }
            if ($order->status == 'completed' || $order->status == 'cancelled') {
                throw new Exception('Order status cannot be updated because it is already completed or cancelled');
            }
            $order->status = $validatedData['status'];
            $order->save();

            return $order;
        } catch (Exception $e) {
            Log::error('OrderService::updateStatus'.$e->getMessage());
            throw $e;
        }
    }

    public function fileUpload()
    {
        //
    }
}
