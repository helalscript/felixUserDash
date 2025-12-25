<?php

namespace App\Services\API\V1\User;

use App\Helpers\Helper;
use App\Models\Addon;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Package;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            $perPage = $request->per_page ?? 10;
            $search = $request->search ?? null;
            $dateFrom = $request->date_from ?? null;
            $dateTo = $request->date_to ?? null;
            
            $query = Order::where('user_id', $this->user->id)
                ->with(['orderable', 'detail']);
            
            // Apply search if provided
            if ($search) {
                $query->where(function ($q) use ($search) {
                    // Search in order_number
                    $q->where('order_number', 'like', '%' . $search . '%')
                        // Search in status
                        ->orWhere('status', 'like', '%' . $search . '%')
                        // Search in related order detail (business name)
                        ->orWhereHas('detail', function ($detailQuery) use ($search) {
                            $detailQuery->where('name', 'like', '%' . $search . '%')
                                ->orWhere('industry', 'like', '%' . $search . '%');
                        })
                        // Search in orderable (Package/Addon) title
                        ->orWhereHasMorph('orderable', [Package::class, Addon::class], function ($morphQuery) use ($search) {
                            $morphQuery->where('title', 'like', '%' . $search . '%');
                        });
                });
            }
            
            // Apply date range filter if provided
            if ($dateFrom) {
                $query->where('start_date', '>=', $dateFrom);
            }
            
            if ($dateTo) {
                $query->where('start_date', '<=', $dateTo);
            }
            
            $orders = $query->paginate($perPage);
            return $orders;
        } catch (Exception $e) {
            Log::error('OrderService::index'.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Store a new resource.
     *
     * @return mixed
     */
    public function store(array $validatedData)
    {
        try {
            DB::beginTransaction();
            $type = isset($validatedData['package_id']) ? 'package' : 'addon';
            $price = $this->getPrice(isset($validatedData['package_id']) ? $validatedData['package_id'] : $validatedData['addon_id'], $type);

            $order = Order::create([
                'user_id' => $this->user->id,
                'orderable_id' => isset($validatedData['package_id']) ? $validatedData['package_id'] : $validatedData['addon_id'],
                'orderable_type' => isset($validatedData['package_id']) ? 'App\Models\Package' : 'App\Models\Addon',
                'order_number' => 'ORD-'.rand(1000, 9999),
                'progress' => 0,
                'start_date' => now(),
                'end_date' => null,
                'amount' => $price,
                'status' => 'pending',
            ]);

            //file upload
            if (isset($validatedData['attachment']) && !empty($validatedData['attachment'])) {
                $validatedData['attachment'] = array_map(function ($attachment) {
                    return Helper::fileUpload(
                        $attachment,
                        'order/details',
                        'order_detail_'.time()
                    );
                }, $validatedData['attachment']);
            }
            // Create order detail
            $orderDetail = OrderDetail::create([
                'order_id' => $order->id,
                'name' => $validatedData['business_name'],
                'industry' => $validatedData['business_industry'],
                'requirement' => $validatedData['business_requirement_description'],
                'voice_preference' => $validatedData['voice_preference'],
                'attachment' => $validatedData['attachment']??null,
                'amount' => $price,
            ]);
            DB::commit();

            return $order;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('OrderService::store'.$e->getMessage());
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
            $order = Order::where('user_id', $this->user->id)
            ->with('orderable','detail','payments','invoices','addons','delivery')
            ->find($id);
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
     * Update a specific resource.
     *
     * @return mixed
     */
    public function update(int $id, array $validatedData)
    {
        try {

        } catch (Exception $e) {
            Log::error('OrderService::update'.$e->getMessage());
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
            $order = Order::where('user_id', $this->user->id)->find($id);
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

    private function getPrice($id, $type)
    {
        if ($type == 'package') {
            $package = Package::find($id);

            return $package->price;
        } elseif ($type == 'addon') {
            $addon = Addon::find($id);

            return $addon->price;
        }
        throw new Exception('Invalid type');
    }

}
