<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoiceable_id',
        'invoiceable_type',
        'invoice_number',
        'start_date',
        'end_date',
        'amount',
        'status',
    ];

    protected $casts = [
        'invoiceable_id' => 'integer',
        'invoiceable_type' => 'string',
        'invoice_number' => 'string',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'amount' => 'decimal:2',
        'status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function payments()
    {
        return $this->morphMany(Payment::class, 'paymentable');
    }
}


