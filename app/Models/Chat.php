<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'status',
    ];

    protected $casts = [
        'type' => 'string',
        'status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the chat users for this chat.
     */
    public function chatUsers()
    {
        return $this->hasMany(ChatUser::class);
    }

    /**
     * Get the messages for this chat.
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}


