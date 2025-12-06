<?php

namespace App\Services\API\V1\User;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class OrderService
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
            
        } catch (Exception $e) {
         Log::error("OrderService::index" . $e->getMessage());
            throw $e;
             
        }
    }

    /**
     * Store a new resource.
     *
     * @param array $validatedData
     * @return mixed
     */
    public function store(array $validatedData)
    {
        try {
            
        } catch (Exception $e) {
         Log::error("OrderService::store" . $e->getMessage());
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
            
        } catch (Exception $e) {
         Log::error("OrderService::show" . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update a specific resource.
     *
     * @param int $id
     * @param array $validatedData
     * @return mixed
     */
    public function update(int $id, array $validatedData)
    {
        try {
            
        } catch (Exception $e) {
         Log::error("OrderService::update" . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete a specific resource.
     *
     * @param int $id
     * @return mixed
     */
    public function destroy(int $id)
    {
        try {
            // Logic to delete a specific resource
        } catch (Exception $e) {
         Log::error("OrderService::destroy" . $e->getMessage());
            throw $e;
        }
    }

}