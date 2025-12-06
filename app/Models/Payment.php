<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'payable_id',
        'payable_type',
        'pay_at',
        'intend_id',
        'amount',
        'status',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'payable_id' => 'integer',
        'payable_type' => 'string',
        'pay_at' => 'datetime',
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
