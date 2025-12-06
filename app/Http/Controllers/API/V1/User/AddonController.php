<?php

namespace App\Http\Controllers\API\V1\User;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Services\API\V1\User\AddonService;
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
            Log::error('AddonController::index'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
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
            Log::error('AddonController::show'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }
}
