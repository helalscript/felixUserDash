<?php

namespace App\Http\Controllers\API\V1\Admin\Package;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Services\API\V1\Admin\Package\PackageService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PackageController extends Controller
{
    public function __construct(protected PackageService $packageService)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $packages = $this->packageService->index($request);

            return Helper::jsonResponse(true, 'Packages fetched successfully', 200, $packages, true);
        } catch (Exception $e) {
            Log::error('Admin\\PackageController::index'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), (int) $e->getCode() ?: 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'explore_link' => 'nullable|string|max:255',
            'specification_text' => 'nullable|string',
            'features' => 'nullable|array',
            'features.*' => 'nullable|string',
            'is_popular' => 'nullable|boolean',
            'position' => 'nullable|integer|min:0',
            'icon_images' => 'nullable|array',
            'icon_images.*' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg|max:5120',
            'status' => 'required|in:active,inactive',
        ]);

        try {
            if ($request->hasFile('icon_images')) {
                $icon_images = [];
                foreach ($request->file('icon_images') as $image) {
                    $icon_images[] = Helper::fileUpload($image, 'packages', 'icon_images');
                }
                $validated['icon_images'] = $icon_images;
            }
            $package = $this->packageService->store($validated);

            return Helper::jsonResponse(true, 'Package created successfully', 201, $package);
        } catch (Exception $e) {
            Log::error('Admin\\PackageController::store'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), (int) $e->getCode() ?: 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $package = $this->packageService->show((int) $id);

            return Helper::jsonResponse(true, 'Package fetched successfully', 200, $package);
        } catch (Exception $e) {
            Log::error('Admin\\PackageController::show'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), (int) $e->getCode() ?: 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'subtitle' => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'explore_link' => 'sometimes|nullable|string|max:255',
            'specification_text' => 'sometimes|nullable|string',
            'features' => 'sometimes|nullable|array',
            'features.*' => 'nullable|string',
            'is_popular' => 'sometimes|boolean',
            'position' => 'sometimes|integer|min:0',
            'icon_images' => 'sometimes|nullable|array',
            'icon_images.*' => 'nullable|string',
            'status' => 'sometimes|required|in:active,inactive',
        ]);
        try {

            $package = $this->packageService->update((int) $id, $validated);

            return Helper::jsonResponse(true, 'Package updated successfully', 200, $package);
        } catch (Exception $e) {
            Log::error('Admin\\PackageController::update'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), (int) $e->getCode() ?: 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $this->packageService->destroy((int) $id);

            return Helper::jsonResponse(true, 'Package deleted successfully', 200);
        } catch (Exception $e) {
            Log::error('Admin\\PackageController::destroy'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), (int) $e->getCode() ?: 500);
        }
    }
}
