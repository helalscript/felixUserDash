<?php

namespace App\Http\Controllers\API\V1\User;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Services\API\V1\User\ChatService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    public function __construct(protected ChatService $chatService)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $chats = $this->chatService->index($request);

            return Helper::jsonResponse(true, 'Chats fetched successfully', 200, $chats->items(), true, $chats);
        } catch (Exception $e) {
            Log::error('ChatController::index' . $e->getMessage());

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
            'message' => 'nullable|string|max:5000',
            'file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,gif,svg,webp|max:20480',
        ]);

        try {
            $result = $this->chatService->store($validatedData);

            return Helper::jsonResponse(true, 'Message sent successfully', 200, $result);
        } catch (Exception $e) {
            Log::error('ChatController::store' . $e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $result = $this->chatService->show($id);

            return Helper::jsonResponse(true, 'Chat messages fetched successfully', 200, $result);
        } catch (Exception $e) {
            Log::error('ChatController::show' . $e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
