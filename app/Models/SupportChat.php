<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportChat extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subject',
        'status',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'subject' => 'string',
        'status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the support chat.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the messages for this support chat.
     */
    public function messages()
    {
        return $this->hasMany(SupportMessage::class);
    }

    public function lastMessage()
    {
        return $this->hasOne(SupportMessage::class)->latestOfMany()
        ->select([
            'support_messages.id',
            'support_messages.support_chat_id',
            'support_messages.message',
            'support_messages.file'
        ]);;
    }
}
