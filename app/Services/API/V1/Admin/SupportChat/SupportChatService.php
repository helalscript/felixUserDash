<?php

namespace App\Services\API\V1\Admin\SupportChat;

use App\Models\SupportChat;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SupportChatService
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
            $searchByName = $request->search_by_name ?? null;
            $perPage = $request->per_page ?? 25;

            $query = SupportChat::query()
                ->select('id', 'user_id', 'subject', 'status', 'created_at', 'updated_at')
                ->when($searchByName, function ($query) use ($searchByName) {
                    $query->whereHas('user', function ($query) use ($searchByName) {
                        $query->where('name', 'like', '%'.$searchByName.'%');
                    });
                })
                ->with('user:id,name,email,avatar,role')
                ->withCount(['messages as unseencount' => function ($query) {
                    $query->whereNull('seen_at')
                        ->where('user_id', '!=', $this->user->id);
                }])
                ->orderBy('id', 'desc');

            $supportChats = $query->paginate($perPage);

            return $supportChats;
        } catch (Exception $e) {
            Log::error('SupportChatService::index'.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Store a new resource.
     *
     * @return mixed
     */
    public function store(array $validatedData)
    {
        try {
            $supportChat = SupportChat::create($validatedData);

            return $supportChat;
        } catch (Exception $e) {
            Log::error('SupportChatService::store'.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Display a specific resource.
     *
     * @return mixed
     */
    public function show(int $id)
    {
        try {
            $supportChat = SupportChat::with('user:id,name,email,avatar,role')
                ->with('messages', function ($query) {
                    $query->orderBy('created_at', 'desc');
                })
                ->find($id);
            if (! $supportChat) {
                throw new Exception('Support chat not found');
            }

            return $supportChat;
        } catch (Exception $e) {
            Log::error('SupportChatService::show'.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Update a specific resource.
     *
     * @return mixed
     */
    public function update(int $id, array $validatedData)
    {
        try {

            $supportChat = SupportChat::find($id);
            if (! $supportChat) {
                throw new Exception('Support chat not found');
            }
            $supportChat->update($validatedData);

            return $supportChat;
        } catch (Exception $e) {
            Log::error('SupportChatService::update'.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete a specific resource.
     *
     * @return mixed
     */
    public function destroy(int $id)
    {
        try {
            $supportChat = SupportChat::find($id);
            if (! $supportChat) {
                throw new Exception('Support chat not found');
            }
            $supportChat->delete();

            return $supportChat;
        } catch (Exception $e) {
            Log::error('SupportChatService::destroy'.$e->getMessage());
            throw $e;
        }
    }
}
