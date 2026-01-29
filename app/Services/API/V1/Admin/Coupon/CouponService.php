<?php

namespace App\Services\API\V1\Admin\Coupon;

use App\Models\Coupon;
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
            $coupon = Coupon::with('users')
                ->withCount('usages')
                ->find($id);
            if (! $coupon) {
                throw new Exception('Coupon not found.');
            }

            $coupon->updateStatus();
            $coupon->refresh();

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
            $checkoutLink = url('/checkout?coupon='.$coupon->code.'&user='.$user->id);

            $coupon->users()->syncWithoutDetaching([
                $user->id => ['checkout_link' => $checkoutLink],
            ]);
        }
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
