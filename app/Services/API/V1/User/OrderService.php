<?php

namespace App\Services\API\V1\User;

use App\Helpers\Helper;
use App\Models\Addon;
use App\Models\Coupon;
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
            $perPage = $request->per_page ?? 100;
            $search = $request->search ?? null;
            $dateFrom = $request->date_from ?? null;
            $dateTo = $request->date_to ?? null;

            $query = Order::where('user_id', $this->user->id)
                ->with(['orderable', 'detail'])
                ->withCount('addons');

            // Apply search if provided
            if ($search) {
                $query->where(function ($q) use ($search) {
                    // Search in order_number
                    $q->where('order_number', 'like', '%'.$search.'%')
                        // Search in status
                        ->orWhere('status', 'like', '%'.$search.'%')
                        // Search in related order detail (business name)
                        ->orWhereHas('detail', function ($detailQuery) use ($search) {
                            $detailQuery->where('name', 'like', '%'.$search.'%')
                                ->orWhere('industry', 'like', '%'.$search.'%');
                        })
                        // Search in orderable (Package/Addon) title
                        ->orWhereHasMorph('orderable', [Package::class, Addon::class], function ($morphQuery) use ($search) {
                            $morphQuery->where('title', 'like', '%'.$search.'%');
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
            $originalPrice = $this->getPrice(isset($validatedData['package_id']) ? $validatedData['package_id'] : $validatedData['addon_id'], $type);

            // Handle coupon if provided
            $coupon = null;
            $discountAmount = 0;
            $finalAmount = $originalPrice;

            if (isset($validatedData['coupon_code']) && !empty($validatedData['coupon_code'])) {
                $coupon = Coupon::where('code', $validatedData['coupon_code'])->first();
                
                if ($coupon) {
                    $coupon->updateStatus();
                    $coupon->refresh();

                    // Validate coupon (min order value, coupon value must not exceed order value)
                    if ($coupon->isValid() && $originalPrice >= $coupon->min_order_value && $coupon->canBeAppliedTo($originalPrice)) {
                        $discountAmount = $coupon->calculateDiscount($originalPrice);
                        $finalAmount = $originalPrice - $discountAmount;
                    } else {
                        throw new Exception('Invalid coupon, order amount does not meet minimum requirement, or coupon value exceeds order value.');
                    }
                } else {
                    throw new Exception('Coupon code not found.');
                }
            }

            $order = Order::create([
                'user_id' => $this->user->id,
                'orderable_id' => isset($validatedData['package_id']) ? $validatedData['package_id'] : $validatedData['addon_id'],
                'orderable_type' => isset($validatedData['package_id']) ? 'App\Models\Package' : 'App\Models\Addon',
                'order_number' => 'ORD-'.rand(1000, 9999),
                'progress' => 0,
                'start_date' => now(),
                'end_date' => null,
                'amount' => $finalAmount,
                'original_amount' => $originalPrice,
                'discount_amount' => $discountAmount,
                'coupon_id' => $coupon ? $coupon->id : null,
                'coupon_code' => $coupon ? $coupon->code : null,
                'coupon_value' => $coupon ? $coupon->value : null,
                'status' => 'pending',
            ]);

            // Coupon usage is recorded only when payment completes (in StripeService/PayPalService)

            // file upload
            if (isset($validatedData['attachment']) && ! empty($validatedData['attachment'])) {
                $validatedData['attachment'] = array_map(function ($attachment) {
                    return Helper::fileUpload(
                        $attachment,
                        'order/details',
                        time().'.'.$attachment->getClientOriginalName()
                    );
                }, $validatedData['attachment']);
            }
            // Create order detail
            $orderDetail = OrderDetail::create([
                'order_id' => $order->id,
                'name' => isset($validatedData['business_name']) ? $validatedData['business_name'] : null,
                'industry' => isset($validatedData['business_industry']) ? $validatedData['business_industry'] : null,
                'requirement' => isset($validatedData['business_requirement_description']) ? $validatedData['business_requirement_description'] : null,
                'voice_preference' => isset($validatedData['voice_preference']) ? $validatedData['voice_preference'] : null,
                'attachment' => isset($validatedData['attachment']) ? $validatedData['attachment'] : null,
                'amount' => $finalAmount,
                'is_filled_by_user' => false,
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
                ->with('orderable', 'detail', 'payments', 'invoices', 'addons', 'delivery')
                ->withCount('addons')
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
            DB::beginTransaction();
            $order = Order::where('user_id', $this->user->id)->find($id);
            if (! $order) {
                throw new Exception('Order not found');
            }
            $order->update($validatedData);
            $orderDetail = OrderDetail::where('order_id', $order->id)->first();
            if (! $orderDetail) {
                throw new Exception('Order detail not found');
            }
            if (isset($validatedData['attachment']) && ! empty($validatedData['attachment'])) {
                $validatedData['attachment'] = array_map(function ($attachment) {
                    return Helper::fileUpload(
                        $attachment,
                        'order/details',
                        time().'.'.$attachment->getClientOriginalName()
                    );
                }, $validatedData['attachment']);
            }
            $orderDetail->update([
                'name' => isset($validatedData['business_name']) ? $validatedData['business_name'] : null,
                'industry' => isset($validatedData['business_industry']) ? $validatedData['business_industry'] : null,
                'requirement' => isset($validatedData['business_requirement_description']) ? $validatedData['business_requirement_description'] : null,
                'voice_preference' => isset($validatedData['voice_preference']) ? $validatedData['voice_preference'] : null,
                'attachment' => isset($validatedData['attachment']) ? $validatedData['attachment'] : null,
                'is_filled_by_user' => true,
            ]);
            DB::commit();

            return $order;
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
