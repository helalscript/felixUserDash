<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'chat_id',
        'user_id',
        'message',
        'file',
        'seen_at',
    ];

    protected $casts = [
        'chat_id' => 'integer',
        'user_id' => 'integer',
        'message' => 'string',
        'file' => 'string',
        'seen_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}


