<?php

namespace App\Http\Controllers\API\V1\Admin\SupportChat;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Services\API\V1\Admin\SupportChat\SupportChatService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SupportChatController extends Controller
{
    public function __construct(protected SupportChatService $supportChatService)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $supportChats = $this->supportChatService->index($request);

            return Helper::jsonResponse(true, 'Support chats fetched successfully', 200, $supportChats, true);
        } catch (Exception $e) {
            Log::error('SupportChatController::index'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'user_id' => 'required|exists:users,id',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string|max:5000',
            'file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,gif,svg,webp|max:20480',
        ]);
        try {
            $supportChat = $this->supportChatService->store($validatedData);

            return Helper::jsonResponse(true, 'Support chat created successfully', 200, $supportChat);
        } catch (Exception $e) {
            Log::error('SupportChatController::store'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $supportChat = $this->supportChatService->show($id);

            return Helper::jsonResponse(true, 'Support chat fetched successfully', 200, $supportChat);
        } catch (Exception $e) {
            Log::error('SupportChatController::show'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validatedData = $request->validate([
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string|max:5000',
            'file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,gif,svg,webp|max:20480',
        ]);
        try {
            $supportChat = $this->supportChatService->update($id, $validatedData);

            return Helper::jsonResponse(true, 'Support chat updated successfully', 200, $supportChat);
        } catch (Exception $e) {
            Log::error('SupportChatController::update'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $supportChat = $this->supportChatService->destroy($id);

            return Helper::jsonResponse(true, 'Support chat deleted successfully', 200, $supportChat);
        } catch (Exception $e) {
            Log::error('SupportChatController::destroy'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }
}
