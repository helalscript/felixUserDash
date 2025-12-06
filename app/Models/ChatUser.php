<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatUser extends Model
{
    use HasFactory;

    protected $table = 'chat_users';

    protected $fillable = [
        'chat_id',
        'user_id',
        'status',
    ];

    protected $casts = [
        'chat_id' => 'integer',
        'user_id' => 'integer',
        'status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}


