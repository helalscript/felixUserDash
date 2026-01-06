<?php

namespace App\Services\API\V1\Admin\User;

use App\Helpers\Helper;
use App\Models\User;
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
            $perPage = $request->per_page ?? 10;
            $users = User::select('id', 'name', 'email', 'phone', 'avatar', 'role', 'created_at')
                ->where('role', '!=', 'admin')
                ->where('email_verified_at', '!=', null)
                ->when($request->filled('search'), function ($query) use ($request) {
                    $query->where('name', 'like', '%'.$request->search.'%')
                        ->orWhere('email', 'like', '%'.$request->search.'%')
                        ->orWhere('phone', 'like', '%'.$request->search.'%');
                })
                ->when($request->filled('role'), function ($query) use ($request) {
                    $query->where('role', $request->role);
                })
                ->withCount('orders')
                ->paginate($perPage);

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
            $user = User::select('id', 'name', 'email', 'phone', 'avatar', 'role', 'created_at')
                ->where('email_verified_at', '!=', null)
                ->findOrFail($id);

            return $user;
        } catch (Exception $e) {
            Log::error('UserService::update'.$e->getMessage());
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
}
