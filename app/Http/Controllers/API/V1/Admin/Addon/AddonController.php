<?php

namespace App\Http\Controllers\API\V1\Admin\Addon;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Services\API\V1\Admin\Addon\AddonService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AddonController extends Controller
{
    public function __construct(protected AddonService $addonService)
    {
        //
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $addons = $this->addonService->index($request);
            return Helper::jsonResponse(true, 'Addons fetched successfully', 200, $addons, true);
        } catch (Exception $e) {
            Log::error('Admin\\AddonController::index'.$e->getMessage());

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
            'sub_title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'status' => 'required|in:active,inactive',
        ]);

        try {
            $addon = $this->addonService->store($validated);

            return Helper::jsonResponse(true, 'Addon created successfully', 201, $addon);
        } catch (Exception $e) {
            Log::error('Admin\\AddonController::store'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), (int) $e->getCode() ?: 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $addon = $this->addonService->show($id);
            return Helper::jsonResponse(true, 'Addon fetched successfully', 200, $addon);
        } catch (Exception $e) {
            Log::error('Admin\\AddonController::show'.$e->getMessage());

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
            'sub_title' => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'status' => 'sometimes|required|in:active,inactive',
        ]);

        try {
            $addon = $this->addonService->update((int) $id, $validated);

            return Helper::jsonResponse(true, 'Addon updated successfully', 200, $addon);
        } catch (Exception $e) {
            Log::error('Admin\\AddonController::update'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), (int) $e->getCode() ?: 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $this->addonService->destroy((int) $id);

            return Helper::jsonResponse(true, 'Addon deleted successfully', 200);
        } catch (Exception $e) {
            Log::error('Admin\\AddonController::destroy'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), (int) $e->getCode() ?: 500);
        }
    }
}
