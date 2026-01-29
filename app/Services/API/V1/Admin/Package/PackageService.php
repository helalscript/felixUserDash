<?php

namespace App\Services\API\V1\Admin\Package;

use App\Models\Package;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PackageService
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

            $query = Package::query()
                ->select('id', 'title', 'subtitle', 'description', 'price', 'explore_link', 'specification_text', 'features', 'is_popular', 'position', 'icon_images', 'status');

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('subtitle', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $query->orderBy('position')->orderBy('id');

            return $query->paginate($per_page);
        } catch (Exception $e) {
            Log::error('Admin\\PackageService::index'.$e->getMessage());
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
            $package = Package::create($validatedData);

            return $package->fresh();
        } catch (Exception $e) {
            Log::error('Admin\\PackageService::store'.$e->getMessage());
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
            $package = Package::select('id', 'title', 'subtitle', 'description', 'price', 'explore_link', 'specification_text', 'features', 'is_popular', 'position', 'icon_images', 'status')
                ->find($id);

            if (! $package) {
                throw new Exception('Package not found', 404);
            }

            return $package;
        } catch (Exception $e) {
            Log::error('Admin\\PackageService::show'.$e->getMessage());
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
            $package = Package::find($id);

            if (! $package) {
                throw new Exception('Package not found', 404);
            }

            $package->update($validatedData);

            return $package->fresh();
        } catch (Exception $e) {
            Log::error('Admin\\PackageService::update'.$e->getMessage());
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
            $package = Package::find($id);

            if (! $package) {
                throw new Exception('Package not found', 404);
            }

            $package->delete();
        } catch (Exception $e) {
            Log::error('Admin\\PackageService::destroy'.$e->getMessage());
            throw $e;
        }
    }
}
