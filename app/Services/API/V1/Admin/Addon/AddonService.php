<?php

namespace App\Services\API\V1\Admin\Addon;

use App\Models\Addon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AddonService
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
            $per_page = $request->per_page ?? 25;

            $query = Addon::query()
                ->select('id', 'title', 'sub_title', 'description', 'price', 'status');

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('sub_title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $query->orderBy('id', 'desc');

            return $query->paginate($per_page);
        } catch (Exception $e) {
            Log::error('Admin\\AddonService::index'.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Store a new resource.
     *
     * @param  array  $validatedData
     * @return mixed
     */
    public function store(array $validatedData)
    {
        try {
            $addon = Addon::create($validatedData);

            return $addon->fresh();
        } catch (Exception $e) {
            Log::error('Admin\\AddonService::store'.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Display a specific resource.
     *
     * @param  int  $id
     * @return mixed
     */
    public function show(int $id)
    {
        try {
            $addon = Addon::select('id', 'title', 'sub_title', 'description', 'price', 'status')
                ->find($id);

            if (! $addon) {
                throw new Exception('Addon not found', 404);
            }

            return $addon;
        } catch (Exception $e) {
            Log::error('Admin\\AddonService::show'.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Update a specific resource.
     *
     * @param  int   $id
     * @param  array $validatedData
     * @return mixed
     */
    public function update(int $id, array $validatedData)
    {
        try {
            $addon = Addon::find($id);

            if (! $addon) {
                throw new Exception('Addon not found', 404);
            }

            $addon->update($validatedData);

            return $addon->fresh();
        } catch (Exception $e) {
            Log::error('Admin\\AddonService::update'.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete a specific resource.
     *
     * @param  int $id
     * @return void
     */
    public function destroy(int $id)
    {
        try {
            $addon = Addon::find($id);

            if (! $addon) {
                throw new Exception('Addon not found', 404);
            }

            $addon->delete();
        } catch (Exception $e) {
            Log::error('Admin\\AddonService::destroy'.$e->getMessage());
            throw $e;
        }
    }
}