<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportMessage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'support_chat_id',
        'user_id',
        'message',
        'file',
        'is_auto_reply',
        'seen_at',
    ];

    protected $casts = [
        'support_chat_id' => 'integer',
        'user_id' => 'integer',
        'message' => 'string',
        'file' => 'string',
        'is_auto_reply' => 'boolean',
        'seen_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the support chat that owns the message.
     */
    public function supportChat()
    {
        return $this->belongsTo(SupportChat::class);
    }

    /**
     * Get the user that owns the message.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getFileAttribute($value): string|null
    {
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
        // Check if the request is an API request
        if (request()->is('api/*') && !empty($value)) {
            // Return the full URL for API requests
            return url($value);
        }

        // Return only the path for web requests
        return $value;
    }
}
