<?php

namespace App\Services\API\V1\User;

use App\Models\Addon;
use App\Models\Order;
use App\Models\OrderAddon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class OrderAddonService
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
            $per_page = $request->per_page ?? 100;
            $query = OrderAddon::query()
            ->with(['addon', 'order', 'payments', 'invoices'])
            ->where('user_id', $this->user->id)
            ->orderBy('id', 'desc');
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%');
                });
            }
            $orderAddons = $query->paginate($per_page);
            return $orderAddons;
        } catch (Exception $e) {
         Log::error("OrderAddonService::index" . $e->getMessage());
            throw $e;
             
        }
    }

    /**
     * Store a new resource.
     *
     * @param array $validatedData
     * @return mixed
     */
    public function store(array $validatedData)
    {
        try {
            DB::beginTransaction();
            //check order exists
            $order = Order::find(isset($validatedData['order_id']) ? $validatedData['order_id'] : null);
            if (! $order) {
                throw new Exception('Order not found');
            }
            //check order status
            // if ($order->status != 'pending') {
            //     throw new Exception('Order is not pending');
            // }
            // get addon details
            $addon = Addon::find(isset($validatedData['addon_id']) ? $validatedData['addon_id'] : null);
            if (!$addon) {
                throw new Exception('Addon not found');
            } 
            $orderAddon = OrderAddon::create([
                'order_id' => $validatedData['order_id'],
                'addon_id' => $addon->id,
                'user_id' => $this->user->id,
                'name' => $addon->title,
                'sub_title' => $addon->sub_title,
                'description' => $addon->description,
                'amount' => $addon->price,
            ]);
            DB::commit();
            return $orderAddon;
        } catch (Exception $e) {
            DB::rollBack();
         Log::error("OrderAddonService::store" . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Display a specific resource.
     *
     * @param int $id
     * @return mixed
     */
    public function show(int $id)
    {
        try {
            $orderAddon = OrderAddon::with(['addon', 'order', 'payments', 'invoices'])
            ->where('user_id', $this->user->id)
            ->find($id);
            if (! $orderAddon) {
                throw new Exception('Order addon not found');
            }
            return $orderAddon;
            
        } catch (Exception $e) {
         Log::error("OrderAddonService::show" . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update a specific resource.
     *
     * @param int $id
     * @param array $validatedData
     * @return mixed
     */
    public function update(int $id, array $validatedData)
    {
        try {
            
        } catch (Exception $e) {
         Log::error("OrderAddonService::update" . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete a specific resource.
     *
     * @param int $id
     * @return mixed
     */
    public function destroy(int $id)
    {
        try {
            $orderAddon = OrderAddon::find($id);
            if (! $orderAddon) {
                throw new Exception('Order addon not found');
            }
            $orderAddon->delete();
            return $orderAddon;
        } catch (Exception $e) {
         Log::error("OrderAddonService::destroy" . $e->getMessage());
            throw $e;
        }
    }

}