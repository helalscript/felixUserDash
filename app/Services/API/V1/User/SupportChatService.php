<?php

namespace App\Services\API\V1\User;

use App\Helpers\Helper;
use App\Models\SupportChat;
use App\Models\SupportMessage;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupportChatService
{
    protected $user;

    public function __construct()
    {
        $this->user = Auth::user();
    }

    /**
     * Fetch all support chats for the authenticated user.
     *
     * @return mixed
     */
    public function index($request)
    {
        try {
            $perPage = $request->per_page ?? 10;

            $query = SupportChat::where('user_id', $this->user->id)
                ->with(['messages' => function ($query) {
                    $query->latest()->limit(1)->with('user:id,name,avatar,role');
                }])
                ->orderBy('updated_at', 'desc');

            $supportChats = $query->paginate($perPage);

            return $supportChats;
        } catch (Exception $e) {
            Log::error('SupportChatService::index'.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Store a new support message.
     *
     * @return mixed
     */
    public function store(array $validatedData)
    {
        try {
            DB::beginTransaction();

            // Find or create support chat for the user
            $supportChat = SupportChat::firstOrCreate(
                ['user_id' => $this->user->id],
                [
                    // 'subject' => $validatedData['subject'] ?? 'Support Request',
                    'status' => 'open',
                ]
            );

            // Handle file upload if provided
            $filePath = null;
            if (isset($validatedData['file']) && $validatedData['file']) {
                $filePath = \App\Helpers\Helper::fileUpload(
                    $validatedData['file'],
                    'support/files',
                    'support_file_'.time()
                );
            }

            // Create user message
            $message = SupportMessage::create([
                'support_chat_id' => $supportChat->id,
                'user_id' => $this->user->id,
                'message' => $validatedData['message'] ?? null,
                'file' => $filePath,
                'is_auto_reply' => false,
            ]);

            // Check if this is the first message in this support chat
            $messageCount = SupportMessage::where('support_chat_id', $supportChat->id)
                ->where('is_auto_reply', false)
                ->count();

            // If this is the first message, send auto-reply
            if ($messageCount === 1) {
                $this->sendAutoReply($supportChat);
            }

            // Update support chat updated_at timestamp
            $supportChat->touch();

            DB::commit();

            // Load relationships
            $message->load('user:id,name,avatar,role');
            $supportChat->load('user:id,name,email,avatar,role');

            return [
                'support_chat' => $supportChat,
                'message' => $message,
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('SupportChatService::store'.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Display a specific support chat with messages.
     *
     * @return mixed
     */
    public function show(int $id)
    {
        try {
            $perPage = request()->per_page ?? 20;

            // Verify user owns this support chat
            $supportChat = SupportChat::where('id', $id)
                ->where('user_id', $this->user->id)
                ->first();

            if (! $supportChat) {
                throw new Exception('Support chat not found or access denied');
            }

            // Get messages with pagination
            $messages = SupportMessage::where('support_chat_id', $id)
                ->with('user:id,name,avatar,role')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            // Mark messages as seen for current user
            SupportMessage::where('support_chat_id', $id)
                ->where('user_id', '!=', $this->user->id)
                ->whereNull('seen_at')
                ->update(['seen_at' => now()]);

            $supportChat->load('user:id,name,email,avatar,role');

            return [
                'support_chat' => $supportChat,
                'messages' => $messages,
            ];
        } catch (Exception $e) {
            Log::error('SupportChatService::show'.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Send automatic reply after first message.
     *
     * @return void
     */
    private function sendAutoReply(SupportChat $supportChat)
    {
        // Get admin user (first admin found)
        $admin = User::where('role', 'admin')->first();

        if (! $admin) {
            Log::warning('No admin found to send auto-reply for support chat: '.$supportChat->id);

            return;
        }

        // Static auto-reply message
        $autoReplyMessage = "Thank you for contacting our support team. We have received your message and will get back to you as soon as possible. Your ticket ID is #{$supportChat->id}.";

        // Create auto-reply message
        SupportMessage::create([
            'support_chat_id' => $supportChat->id,
            'user_id' => $admin->id,
            'message' => $autoReplyMessage,
            'file' => null,
            'is_auto_reply' => true,
        ]);
    }

    public function getMessages($request)
    {
        try {
            $perPage = $request->per_page ?? 100;
            $supportChat = SupportChat::where('user_id', $this->user->id)
                ->with(['messages'=> function ($query) use ($perPage) {
                    $query->orderBy('id', 'asc')
                    ->with('user:id,name,avatar,role')
                    ->with('user:id,name,avatar,role')
                        ->paginate($perPage);
                }])
                ->first();
            // if support chat not found, create a new support chat
            if (! $supportChat) {
                // create a new support chat
                $supportChat = SupportChat::create([
                    'user_id' => $this->user->id,
                    'status' => 'open',
                ]);
                $supportChat->load(['messages'=>function($query){
                    $query->orderBy('id', 'ase')->with('user:id,name,avatar,role');
                }]);
            }
            // dd($supportChat->toArray());
            return $supportChat;
        } catch (Exception $e) {
            Log::error('SupportChatService::getMessages'.$e->getMessage());
            throw new Exception('Failed to get support chat messages');
        }
    }

    public function sendMessage($validatedData)
    {
        try {
            DB::beginTransaction();
            // Find or create support chat for the user
            $supportChat = SupportChat::where('user_id', $this->user->id)
                ->with('messages')
                ->first();
            // if support chat not found, create a new support chat
            if (! $supportChat) {
                $supportChat = SupportChat::create([
                    'user_id' => $this->user->id,
                    'status' => 'open',
                ]);
                $supportChat->load('messages');
            }

            // Handle file upload if provided
            $filePath = null;
            if (isset($validatedData['file'])) {
                $filePath = Helper::fileUpload(
                    $validatedData['file'],
                    'support/files',
                    'support_file_'.time()
                );
            }
            // Create user message
            $message = SupportMessage::create([
                'support_chat_id' => $supportChat->id,
                'user_id' => $this->user->id,
                'message' => $validatedData['message'],
                'file' => $filePath,
                'is_auto_reply' => false,
            ]);
            // Check if this is the first message in this support chat
            $messageCount = SupportMessage::where('support_chat_id', $supportChat->id)
                ->where('is_auto_reply', false)
                ->count();

            // If this is the first message, send auto-reply
            if ($messageCount === 1) {
                $this->sendAutoReply($supportChat);
            }

            DB::commit();

            return $message;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('SupportChatService::sendMessage'.$e->getMessage());
            throw new Exception('Failed to send message');
        }
    }
}
