<?php

namespace App\Services\API\V1\User;

use App\Models\Package;
use Exception;
use Illuminate\Support\Facades\Log;

class PackageService
{
    /**
     * Fetch all resources.
     *
     * @return mixed
     */
    public function index($request)
    {
        try {
            $per_page = $request->per_page ?? 25;
            $packages = Package::select('id', 'title', 'subtitle', 'description', 'price', 'explore_link', 'specification_text', 'features', 'is_popular', 'position', 'icon_images', 'status')
                ->paginate($per_page);

            return $packages;

        } catch (Exception $e) {
            Log::error('PackageService::index'.$e->getMessage());
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
            $package = Package::select('id', 'title', 'subtitle', 'description', 'price', 'explore_link', 'specification_text', 'features', 'is_popular', 'position', 'icon_images', 'status')
                ->find($id);
            if (! $package) {
                throw new Exception('Package not found', 404);
            }
            return $package;
        } catch (Exception $e) {
            Log::error('PackageService::show'.$e->getMessage());
            throw $e;
        }
    }
}
