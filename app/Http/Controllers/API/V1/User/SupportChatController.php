<?php

namespace App\Http\Controllers\API\V1\User;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Services\API\V1\User\SupportChatService;
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
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string|max:5000',
            'file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,gif,svg,webp|max:20480',
        ]);

        try {
            $result = $this->supportChatService->store($validatedData);

            return Helper::jsonResponse(true, 'Support message sent successfully', 200, $result);
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
            $result = $this->supportChatService->show($id);

            return Helper::jsonResponse(true, 'Support chat messages fetched successfully', 200, $result);
        } catch (Exception $e) {
            Log::error('SupportChatController::show'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    public function getMessages(Request $request)
    {
        try {
            $result = $this->supportChatService->getMessages($request);
            return Helper::jsonResponse(true, 'Support chat messages fetched successfully', 200, $result);
        } catch (Exception $e) {
            Log::error('SupportChatController::getMessages'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    public function sendMessage(Request $request)
    {
        $validatedData = $request->validate([
            'message' => 'required|string|max:5000',
            'file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,gif,svg,webp|max:5120',
        ]);
        try {
            $result = $this->supportChatService->sendMessage($validatedData);

            return Helper::jsonResponse(true, 'Message sent successfully', 200, $result);
        } catch (Exception $e) {
            Log::error('SupportChatController::sendMessage'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }
}
