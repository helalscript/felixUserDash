<?php

namespace App\Http\Controllers\API\V1\Admin\User;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Services\API\V1\Admin\User\UserService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function __construct(protected UserService $userService)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $users = $this->userService->index($request);

            return Helper::jsonResponse(true, 'Users fetched successfully', 200, $users, true);
        } catch (Exception $e) {
            Log::error('Admin\\UserController::index'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
        ]);
        try {
            $user = $this->userService->store($validatedData);

            return Helper::jsonResponse(true, 'User created successfully', 200, $user);
        } catch (Exception $e) {
            Log::error('Admin\\UserController::store'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $user = $this->userService->show($id);

            return Helper::jsonResponse(true, 'User fetched successfully', 200, $user);
        } catch (Exception $e) {
            Log::error('Admin\\UserController::show'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$id,
            'password' => 'nullable|string|min:8',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
        ]);
        try {
            $user = $this->userService->update($id, $validatedData);

            return Helper::jsonResponse(true, 'User updated successfully', 200, $user);
        } catch (Exception $e) {
            Log::error('Admin\\UserController::update'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $this->userService->destroy($id);

            return Helper::jsonResponse(true, 'User deleted successfully', 200);
        } catch (Exception $e) {
            Log::error('Admin\\UserController::destroy'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }
}
