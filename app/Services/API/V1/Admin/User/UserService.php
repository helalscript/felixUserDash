<?php

namespace App\Services\API\V1\Admin\User;

use App\Helpers\Helper;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserService
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
            $perPage = $request->per_page ?? 1000;
            $users = User::select(
                'id',
                'name',
                'email',
                'user_name',
                'phone',
                'avatar',
                'role',
                'created_at',
                'last_login_at',
                'login_count',
                'last_login_ip',
                'location',
                'timezone',
                'last_seen'
            )
                ->where('role', '!=', 'admin')
                ->where('email_verified_at', '!=', null)
                ->when($request->filled('search'), function ($query) use ($request) {
                    $search = $request->search;
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%')
                            ->orWhere('user_name', 'like', '%'.$search.'%')
                            ->orWhere('phone', 'like', '%'.$search.'%')
                            ->orWhere('location', 'like', '%'.$search.'%')
                            ->orWhereDate('created_at', 'like', '%'.$search.'%')
                            ->orWhereDate('last_login_at', 'like', '%'.$search.'%');
                    });
                })
                ->when($request->filled('role'), function ($query) use ($request) {
                    $query->where('role', $request->role);
                })
                ->withCount(['orders' => function ($query) {
                    $query->where('payment_status', 'paid');
                }])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $users->getCollection()->transform(fn ($user) => $this->transformLastLoginAt($user));

            return $users;
        } catch (Exception $e) {
            Log::error('UserService::index'.$e->getMessage());
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

            $validatedData['password'] = bcrypt($validatedData['password']);
            if (isset($validatedData['avatar']) && ! empty($validatedData['avatar'])) {
                $validatedData['avatar'] = Helper::fileUpload($validatedData['avatar'], 'user/avatar', getFileName($validatedData['avatar']));
            }
            $validatedData['email_verified_at'] = now();
            $validatedData['role'] = 'user';
            $validatedData['status'] = 'active';
            $user = User::create($validatedData);
            DB::commit();

            return $user;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('UserService::store'.$e->getMessage());
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
            $user = User::select('id', 'name', 'email', 'user_name', 'phone', 'avatar', 'role', 'created_at', 'last_login_at', 'login_count', 'last_login_ip', 'last_seen', 'location', 'gender', 'brithdate', 'timezone', 'status')
                ->where('email_verified_at', '!=', null)
                ->where('role', '!=', 'admin')
                // ->withCount('orders')
                ->withCount(['orders' => function ($query) {
                    $query->where('payment_status', 'paid');
                }])
                ->findOrFail($id);
            $user = $this->transformLastLoginAt($user);

            return $user;
        } catch (Exception $e) {
            Log::error('UserService::show'.$e->getMessage());
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
            $user = User::where('email_verified_at', '!=', null)->findOrFail($id);
            if (! empty($validatedData['password'])) {
                $validatedData['password'] = bcrypt($validatedData['password']);
                $validatedData['password_changed_at'] = now();
            } elseif (array_key_exists('password', $validatedData)) {
                unset($validatedData['password']);
            }
            if (isset($validatedData['avatar']) && ! empty($validatedData['avatar'])) {
                if (! empty($user->avatar)) {
                    Helper::fileDelete(public_path($user->getRawOriginal('avatar')));
                }
                $validatedData['avatar'] = Helper::fileUpload($validatedData['avatar'], 'user/avatar', getFileName($validatedData['avatar']));
            } else {
                $validatedData['avatar'] = $user->avatar;
            }

            $user->update($validatedData);

            // If password was changed, revoke all web sessions (forces re-login)
            if (isset($validatedData['password_changed_at'])) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }

            return $user;
        } catch (Exception $e) {
            Log::error('UserService::update'.$e->getMessage());
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
            $user = User::where('email_verified_at', '!=', null)->findOrFail($id);
            $user->delete();

            return $user;
        } catch (Exception $e) {
            Log::error('UserService::destroy'.$e->getMessage());
            throw $e;
        }
    }

    public function resetPassword(string $password, int $id)
    {
        try {
            DB::beginTransaction();
            $user = User::where('email_verified_at', '!=', null)->findOrFail($id);
            $user->password = bcrypt($password);
            $user->password_changed_at = now();
            $user->save();

            // Revoke all web sessions for this user (forces re-login)
            DB::table('sessions')->where('user_id', $user->id)->delete();

            DB::commit();

            return $user;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('UserService::resetPassword'.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Convert last_login_at to user's timezone before returning.
     * If user has no timezone, return as-is (app default).
     */
    private function transformLastLoginAt(User $user): User
    {

        if ($user->last_seen) {
            $user->is_online = $user->last_seen ? Carbon::parse($user->last_seen)->gte(now()->subMinutes(2)) : false;
        }

        if ($user->last_login_at && $user->timezone) {
            try {
                $user->last_login_at = Carbon::parse($user->last_login_at)
                    ->timezone($user->timezone);

            } catch (\Exception $e) {
                Log::warning('UserService::transformLastLoginAt invalid timezone: '.$user->timezone);
            }
        }

        return $user;
    }
}
