<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderDetail extends Model
{
    use HasFactory;

    protected $table = 'order_details';

    protected $fillable = [
        'order_id',
        'name',
        'industry',
        'requirement',
        'voice_preference',
        'attachment',
        'amount',
        'status',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'name' => 'string',
        'industry' => 'string',
        'requirement' => 'string',
        'voice_preference' => 'string',
        'attachment' => 'array',
        'amount' => 'decimal:2',
        'status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function getAttachmentAttribute($value): ?array
    {
        if (is_null($value)) {
            return null;
        }

        // When using 'array' cast, $value is already an array; if not, decode JSON.
        $attachments = is_array($value) ? $value : json_decode($value, true) ?? [];

        return array_map(function ($attachment) {
            return filter_var($attachment, FILTER_VALIDATE_URL) ? $attachment : url($attachment);
        }, $attachments);
    }
}


