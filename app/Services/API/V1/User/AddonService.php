<?php

namespace App\Services\API\V1\User;

use App\Models\Addon;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

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
            $addons = Addon::select('id', 'title', 'sub_title', 'description', 'price', 'status')
                ->paginate($per_page);
            return $addons;
        } catch (Exception $e) {
         Log::error("AddonService::index" . $e->getMessage());
            throw $e;
             
        }
    }


    /**
     * Display a specific resource.
     *
     * @param int $id
     * @return mixed
     */
    public function show(int $id)
    {
        try {
            $addon = Addon::select('id', 'title', 'sub_title', 'description', 'price', 'status')
                ->find($id);
            if (! $addon) {
                throw new Exception('Addon not found');
            }
            return $addon;
        } catch (Exception $e) {
         Log::error("AddonService::show" . $e->getMessage());
            throw $e;
        }
    }


}