<?php

namespace App\Http\Controllers\API\V1\User;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Services\API\V1\User\PackageService;
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
            Log::error('PackageController::index'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $package = $this->packageService->show($id);

            return Helper::jsonResponse(true, 'Package fetched successfully', 200, $package);
        } catch (Exception $e) {
            Log::error('PackageController::show'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }
}
