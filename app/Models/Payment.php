<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'paymentable_id',
        'paymentable_type',
        'paid_at',
        'intent_id',
        'currency',
        'failure_reason',
        'amount',
        'status',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'paymentable_id' => 'integer',
        'paymentable_type' => 'string',
        'paid_at' => 'datetime',
        'intent_id' => 'string',
        'currency' => 'string',
        'failure_reason' => 'string',
        'amount' => 'decimal:2',
        'status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function paymentable()
    {
        return $this->morphTo();
    }
}
