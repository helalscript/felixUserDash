<?php

namespace App\Services\API\V1\User;

use App\Models\Chat;
use App\Models\ChatUser;
use App\Models\Message;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ChatService
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
            $perPage = $request->per_page ?? 10;
            
            // Get all chats where the current user is a participant
            $chatIds = ChatUser::where('user_id', $this->user->id)
                ->pluck('chat_id');
            
            $chats = Chat::whereIn('id', $chatIds)
                ->with(['chatUsers.user' => function ($query) {
                    $query->select('id', 'name', 'email', 'avatar', 'role');
                }])
                ->with(['messages' => function ($query) {
                    $query->latest()->limit(1)->with('user:id,name,avatar');
                }])
                ->orderBy('updated_at', 'desc')
                ->paginate($perPage);
            
            return $chats;
        } catch (Exception $e) {
            Log::error("ChatService::index" . $e->getMessage());
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
            DB::beginTransaction();
            
            $receiverId = $validatedData['user_id'];
            $receiver = User::findOrFail($receiverId);
            
            // Validate permissions
            // Admin can message any user
            // Regular user can only message admin
            if ($this->user->role === 'user' && $receiver->role !== 'admin') {
                throw new Exception('Users can only message admins');
            }
            
            // Find existing chat between these two users
            $chat = $this->findOrCreateChat($receiverId);
            
            // Handle file upload if provided
            $filePath = null;
            if (isset($validatedData['file']) && $validatedData['file']) {
                $filePath = \App\Helpers\Helper::fileUpload(
                    $validatedData['file'],
                    'chat/files',
                    'chat_file_' . time()
                );
            }
            
            // Create message
            $message = Message::create([
                'chat_id' => $chat->id,
                'user_id' => $this->user->id,
                'message' => $validatedData['message'] ?? null,
                'file' => $filePath,
            ]);
            
            // Update chat updated_at timestamp
            $chat->touch();
            
            DB::commit();
            
            // Load relationships
            $message->load('user:id,name,avatar,role');
            
            return [
                'chat' => $chat->load(['chatUsers.user:id,name,email,avatar,role']),
                'message' => $message,
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("ChatService::store" . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Find existing chat or create new one
     *
     * @param int $receiverId
     * @return Chat
     */
    private function findOrCreateChat(int $receiverId)
    {
        // Find existing chat between current user and receiver
        // For single chats, we need exactly 2 participants
        $existingChat = Chat::where('type', 'single')
            ->whereHas('chatUsers', function ($query) {
                $query->where('user_id', $this->user->id);
            })
            ->whereHas('chatUsers', function ($query) use ($receiverId) {
                $query->where('user_id', $receiverId);
            })
            ->withCount('chatUsers')
            ->having('chat_users_count', '=', 2)
            ->first();
        
        if ($existingChat) {
            return $existingChat;
        }
        
        // Create new chat
        $chat = Chat::create([
            'type' => 'single',
            'status' => 'active',
        ]);
        
        // Add both users to chat
        ChatUser::create([
            'chat_id' => $chat->id,
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);
        
        ChatUser::create([
            'chat_id' => $chat->id,
            'user_id' => $receiverId,
            'status' => 'active',
        ]);
        
        return $chat;
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
            $perPage = request()->per_page ?? 20;
            
            // Verify user is part of this chat
            $chatUser = ChatUser::where('chat_id', $id)
                ->where('user_id', $this->user->id)
                ->first();
            
            if (!$chatUser) {
                throw new Exception('Chat not found or access denied');
            }
            
            // Get chat with participants
            $chat = Chat::with(['chatUsers.user' => function ($query) {
                $query->select('id', 'name', 'email', 'avatar', 'role');
            }])
            ->findOrFail($id);
            
            // Get messages with pagination
            $messages = Message::where('chat_id', $id)
                ->with('user:id,name,avatar,role')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
            
            // Mark messages as seen for current user
            Message::where('chat_id', $id)
                ->where('user_id', '!=', $this->user->id)
                ->whereNull('seen_at')
                ->update(['seen_at' => now()]);
            
            return [
                'chat' => $chat,
                'messages' => $messages,
            ];
        } catch (Exception $e) {
            Log::error("ChatService::show" . $e->getMessage());
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
         Log::error("ChatService::update" . $e->getMessage());
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
         Log::error("ChatService::destroy" . $e->getMessage());
            throw $e;
        }
    }

}