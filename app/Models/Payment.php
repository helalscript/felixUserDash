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
        'intend_id',
        'amount',
        'status',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'paymentable_id' => 'integer',
        'paymentable_type' => 'string',
        'paid_at' => 'datetime',
        'intend_id' => 'string',
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
