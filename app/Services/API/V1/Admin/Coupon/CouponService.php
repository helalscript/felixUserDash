<?php

namespace App\Services\API\V1\Admin\Coupon;

use App\Models\Addon;
use App\Models\Coupon;
use App\Models\CouponCheckoutLink;
use App\Models\Package;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CouponService
{
    protected $user;

    public function __construct()
    {
        $this->user = Auth::user();
    }

    /**
     * Fetch all coupons with status and usage information.
     *
     * @return mixed
     */
    public function index($request)
    {
        try {
            $perPage = $request->per_page ?? 100;

            $coupons = Coupon::withCount('usages')
                ->withCount('users')
                ->when($request->filled('search'), function ($query) use ($request) {
                    $search = $request->search;
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', '%'.$search.'%')
                            ->orWhere('code', 'like', '%'.$search.'%');
                    });
                })
                ->when($request->filled('status'), function ($query) use ($request) {
                    $query->where('status', $request->status);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            // Update status for each coupon and add computed fields
            $coupons->getCollection()->transform(function ($coupon) {
                $coupon->updateStatus();
                $coupon->refresh();

                return [
                    'id' => $coupon->id,
                    'name' => $coupon->name,
                    'code' => $coupon->code,
                    'type' => $coupon->type,
                    'value' => $coupon->value,
                    'min_order_value' => $coupon->min_order_value,
                    'usage_limit' => $coupon->usage_limit,
                    'is_unlimited_time' => $coupon->is_unlimited_time,
                    'start_date' => $coupon->start_date,
                    'start_time' => $coupon->start_time,
                    'end_date' => $coupon->end_date,
                    'end_time' => $coupon->end_time,
                    'status' => $coupon->status,
                    'created_at' => $coupon->created_at,
                    'usages_count' => $coupon->usages_count,
                    'users_count' => $coupon->users_count,
                    'time_left' => $coupon->time_left,
                ];
            });

            return $coupons;
        } catch (Exception $e) {
            Log::error('CouponService::index'.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Store a new coupon.
     *
     * @return mixed
     */
    public function store(array $validatedData)
    {
        try {
            DB::beginTransaction();

            // Generate unique coupon code if not provided
            if (empty($validatedData['code'])) {
                $validatedData['code'] = $this->generateUniqueCode();
            } else {
                // Check if code already exists
                if (Coupon::where('code', $validatedData['code'])->exists()) {
                    throw new Exception('Coupon code already exists.');
                }
            }

            // Handle unlimited time
            if (isset($validatedData['is_unlimited_time']) && $validatedData['is_unlimited_time']) {
                $validatedData['start_date'] = null;
                $validatedData['start_time'] = null;
                $validatedData['end_date'] = null;
                $validatedData['end_time'] = null;
            } else {
                // Convert to Malaysian timezone
                if (isset($validatedData['start_date']) && isset($validatedData['start_time'])) {
                    $startDateTime = Carbon::parse($validatedData['start_date'].' '.$validatedData['start_time'], 'Asia/Kuala_Lumpur');
                    $validatedData['start_date'] = $startDateTime->format('Y-m-d');
                    $validatedData['start_time'] = $startDateTime->format('H:i:s');
                }
                if (isset($validatedData['end_date']) && isset($validatedData['end_time'])) {
                    $endDateTime = Carbon::parse($validatedData['end_date'].' '.$validatedData['end_time'], 'Asia/Kuala_Lumpur');
                    $validatedData['end_date'] = $endDateTime->format('Y-m-d');
                    $validatedData['end_time'] = $endDateTime->format('H:i:s');
                }
            }

            // Handle unlimited usage
            if (isset($validatedData['is_unlimited_usage']) && $validatedData['is_unlimited_usage']) {
                $validatedData['usage_limit'] = null;
            }
            unset($validatedData['is_unlimited_usage']);

            // Set initial status
            $now = Carbon::now('Asia/Kuala_Lumpur');
            if ($validatedData['is_unlimited_time'] ?? false) {
                $validatedData['status'] = 'active';
            } elseif (isset($validatedData['start_date']) && isset($validatedData['start_time'])) {
                $startDateTime = Carbon::parse($validatedData['start_date'].' '.$validatedData['start_time'], 'Asia/Kuala_Lumpur');
                $validatedData['status'] = $now->lt($startDateTime) ? 'scheduled' : 'active';
            } else {
                $validatedData['status'] = 'active';
            }

            $coupon = Coupon::create($validatedData);

            // Assign users if provided
            if (isset($validatedData['user_ids']) && is_array($validatedData['user_ids'])) {
                $this->assignUsers($coupon, $validatedData['user_ids']);
            }

            DB::commit();

            return $coupon->load('users');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('CouponService::store'.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Display a specific coupon.
     *
     * @return mixed
     */
    public function show(int $id)
    {
        try {
            $coupon = Coupon::with([
                'users',
                'checkoutLinks' => fn ($q) => $q->with(['user:id,name,email', 'package:id,title,subtitle,price', 'addon:id,title,sub_title,price']),
                'usages' => function ($query) {
                    $query->with([
                        'user:id,name,email,phone',
                        'order' => fn ($q) => $q->with('orderable'),
                        'orderAddon' => fn ($q) => $q->with(['addon', 'order.orderable']),
                    ])->orderBy('used_at', 'desc');
                },
            ])
                ->withCount('usages')
                ->find($id);

            if (! $coupon) {
                throw new Exception('Coupon not found.');
            }

            $coupon->updateStatus();
            $coupon->refresh();

            // Map usages BEFORE refresh clears relations - re-load with relations for mapping
            $coupon->load(['usages' => function ($query) {
                $query->with([
                    'user:id,name,email,phone',
                    'order' => fn ($q) => $q->with('orderable'),
                    'orderAddon' => fn ($q) => $q->with(['addon', 'order.orderable']),
                ])->orderBy('used_at', 'desc');
            }]);

            // Add user and package/plan info to each usage for the details page
            $coupon->setRelation('usages', $coupon->usages->map(function ($usage) {
                $usageData = [
                    'id' => $usage->id,
                    'coupon_id' => $usage->coupon_id,
                    'user_id' => $usage->user_id,
                    'order_id' => $usage->order_id,
                    'order_addon_id' => $usage->order_addon_id,
                    'used_at' => $usage->used_at,
                    'user' => null,
                    'plan' => null,
                    'applied_to' => null,
                    'order_number' => null,
                    'amount' => null,
                ];

                // User info - কোন User এই coupon ব্যবহার করেছে
                if ($usage->relationLoaded('user') && $usage->user) {
                    $usageData['user'] = [
                        'id' => $usage->user->id,
                        'name' => $usage->user->name,
                        'email' => $usage->user->email,
                        'phone' => $usage->user->phone ?? null,
                    ];
                }

                // Order (Package/Plan purchase) - কোন Package / Plan এ apply হয়েছে
                if ($usage->order_id && $usage->relationLoaded('order') && $usage->order) {
                    $order = $usage->order;
                    $usageData['order_number'] = $order->order_number;
                    $usageData['amount'] = $order->amount;

                    if ($order->relationLoaded('orderable') && $order->orderable) {
                        $orderable = $order->orderable;
                        $usageData['applied_to'] = $orderable instanceof \App\Models\Package ? 'package' : 'addon';

                        if ($orderable instanceof \App\Models\Package) {
                            $usageData['plan'] = [
                                'type' => 'package',
                                'id' => $orderable->id,
                                'title' => $orderable->title ?? null,
                                'subtitle' => $orderable->subtitle ?? null,
                                'price' => $orderable->price ?? null,
                            ];
                        } else {
                            $usageData['plan'] = [
                                'type' => 'addon',
                                'id' => $orderable->id,
                                'title' => $orderable->title ?? $orderable->sub_title ?? null,
                                'sub_title' => $orderable->sub_title ?? null,
                                'price' => $orderable->price ?? null,
                            ];
                        }
                    }
                }

                // OrderAddon - addon purchase, parent order has the package/plan
                if ($usage->order_addon_id && $usage->relationLoaded('orderAddon') && $usage->orderAddon) {
                    $orderAddon = $usage->orderAddon;
                    $usageData['amount'] = $orderAddon->amount;
                    $usageData['applied_to'] = 'addon';

                    $usageData['plan'] = [
                        'type' => 'addon',
                        'id' => $orderAddon->addon?->id ?? $orderAddon->addon_id,
                        'title' => $orderAddon->addon?->title ?? $orderAddon->name,
                        'sub_title' => $orderAddon->addon?->sub_title ?? $orderAddon->sub_title ?? null,
                        'price' => $orderAddon->addon?->price ?? $orderAddon->amount ?? null,
                    ];

                    if ($orderAddon->relationLoaded('order') && $orderAddon->order?->relationLoaded('orderable') && $orderAddon->order->orderable) {
                        $parentPackage = $orderAddon->order->orderable;
                        if ($parentPackage instanceof \App\Models\Package) {
                            $usageData['parent_package'] = [
                                'id' => $parentPackage->id,
                                'title' => $parentPackage->title ?? null,
                                'subtitle' => $parentPackage->subtitle ?? null,
                                'price' => $parentPackage->price ?? null,
                            ];
                        }
                        $usageData['order_number'] = $orderAddon->order->order_number;
                    }
                }

                return $usageData;
            }));

            return $coupon;
        } catch (Exception $e) {
            Log::error('CouponService::show'.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Update a specific coupon.
     *
     * @return mixed
     */
    public function update(int $id, array $validatedData)
    {
        try {
            DB::beginTransaction();

            $coupon = Coupon::find($id);
            if (! $coupon) {
                throw new Exception('Coupon not found.');
            }

            // Handle code uniqueness if changed
            if (isset($validatedData['code']) && $validatedData['code'] !== $coupon->code) {
                if (Coupon::where('code', $validatedData['code'])->where('id', '!=', $id)->exists()) {
                    throw new Exception('Coupon code already exists.');
                }
            }

            // Handle unlimited time
            if (isset($validatedData['is_unlimited_time']) && $validatedData['is_unlimited_time']) {
                $validatedData['start_date'] = null;
                $validatedData['start_time'] = null;
                $validatedData['end_date'] = null;
                $validatedData['end_time'] = null;
            } else {
                // Convert to Malaysian timezone
                if (isset($validatedData['start_date']) && isset($validatedData['start_time'])) {
                    $startDateTime = Carbon::parse($validatedData['start_date'].' '.$validatedData['start_time'], 'Asia/Kuala_Lumpur');
                    $validatedData['start_date'] = $startDateTime->format('Y-m-d');
                    $validatedData['start_time'] = $startDateTime->format('H:i:s');
                }
                if (isset($validatedData['end_date']) && isset($validatedData['end_time'])) {
                    $endDateTime = Carbon::parse($validatedData['end_date'].' '.$validatedData['end_time'], 'Asia/Kuala_Lumpur');
                    $validatedData['end_date'] = $endDateTime->format('Y-m-d');
                    $validatedData['end_time'] = $endDateTime->format('H:i:s');
                }
            }

            // Handle unlimited usage
            if (isset($validatedData['is_unlimited_usage']) && $validatedData['is_unlimited_usage']) {
                $validatedData['usage_limit'] = null;
            }
            unset($validatedData['is_unlimited_usage']);

            // Update status if needed
            if (! isset($validatedData['status']) || $validatedData['status'] !== 'deactivated') {
                $now = Carbon::now('Asia/Kuala_Lumpur');
                if ($validatedData['is_unlimited_time'] ?? $coupon->is_unlimited_time) {
                    $validatedData['status'] = 'active';
                } elseif (isset($validatedData['start_date']) && isset($validatedData['start_time'])) {
                    $startDateTime = Carbon::parse($validatedData['start_date'].' '.$validatedData['start_time'], 'Asia/Kuala_Lumpur');
                    $validatedData['status'] = $now->lt($startDateTime) ? 'scheduled' : 'active';
                }
            }

            $coupon->update($validatedData);

            // Update user assignments if provided
            if (isset($validatedData['user_ids'])) {
                $coupon->users()->sync([]);
                if (is_array($validatedData['user_ids']) && ! empty($validatedData['user_ids'])) {
                    $this->assignUsers($coupon, $validatedData['user_ids']);
                }
            }

            $coupon->updateStatus();
            $coupon->refresh();

            DB::commit();

            return $coupon->load('users');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('CouponService::update'.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete or deactivate a coupon.
     *
     * @return mixed
     */
    public function destroy(int $id)
    {
        try {
            $coupon = Coupon::find($id);

            if (! $coupon) {
                throw new Exception('Coupon not found.');
            }
            // Instead of deleting, deactivate it
            $coupon->status = 'deactivated';
            $coupon->save();

            return $coupon;
        } catch (Exception $e) {
            Log::error('CouponService::destroy'.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Assign users to a coupon and generate checkout links.
     */
    public function assignUsers(Coupon $coupon, array $userIds)
    {
        $users = User::whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            $type = $coupon->type === 'package' ? 'package' : 'addon';
            $checkoutLink = config('services.frontend_url').'/'.$type.'?coupon='.$coupon->code.'&user='.$user->id;

            $coupon->users()->syncWithoutDetaching([
                $user->id => ['checkout_link' => $checkoutLink],
            ]);
        }
    }

    /**
     * Generate a checkout link for a specific user + package/addon + coupon.
     * User clicks the link and goes directly to checkout with coupon applied.
     *
     * @param  int|null  $packageId
     * @param  int|null  $addonId
     * @return CouponCheckoutLink
     */
    public function generateCheckoutLink(int $couponId, int $userId, ?int $packageId = null, ?int $addonId = null): CouponCheckoutLink
    {
        if (! $packageId && ! $addonId) {
            throw new Exception('Either package_id or addon_id is required.');
        }
        if ($packageId && $addonId) {
            throw new Exception('Provide either package_id or addon_id, not both.');
        }

        $coupon = Coupon::find($couponId);
        if (! $coupon) {
            throw new Exception('Coupon not found.');
        }

        $user = User::find($userId);
        if (! $user) {
            throw new Exception('User not found.');
        }

        $price = 0;
        $params = ['coupon' => $coupon->code, 'user' => $userId];

        if ($packageId) {
            $package = Package::find($packageId);
            if (! $package) {
                throw new Exception('Package not found.');
            }
            $price = (float) $package->price;
            $params['package'] = $packageId;
        } elseif ($addonId) {
            $addon = Addon::find($addonId);
            if (! $addon) {
                throw new Exception('Addon not found.');
            }
            $price = (float) $addon->price;
            $params['addon'] = $addonId;
        }

        $coupon->updateStatus();
        $coupon->refresh();
        if (! $coupon->isValid()) {
            throw new Exception('Coupon is not valid (expired, exhausted, or inactive).');
        }

        if ($price < (float) $coupon->min_order_value) {
            $product = $packageId ? 'Package' : 'Addon';
            throw new Exception($product.' price does not meet minimum order value of $'.number_format($coupon->min_order_value, 2));
        }

        // Ensure user is assigned to coupon
        $coupon->users()->syncWithoutDetaching([$userId]);
        //check type is order or addon
        $type = $packageId ? 'package' : 'addon';
        $checkoutLink = config('services.frontend_url').'/'.$type.'?'.http_build_query($params);

        return CouponCheckoutLink::updateOrCreate(
            [
                'coupon_id' => $couponId,
                'user_id' => $userId,
                'package_id' => $packageId,
                'addon_id' => $addonId,
            ],
            ['checkout_link' => $checkoutLink]
        );
    }

    /**
     * Get all checkout links for a coupon.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getCheckoutLinks(int $couponId)
    {
        return CouponCheckoutLink::where('coupon_id', $couponId)
            ->with(['user:id,name,email', 'package:id,title,price', 'addon:id,title,price'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Generate a unique coupon code.
     */
    private function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (Coupon::where('code', $code)->exists());

        return $code;
    }
}
