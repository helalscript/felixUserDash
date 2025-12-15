<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderDelivery extends Model
{
    use HasFactory;

    protected $table = 'order_deliveries';

    protected $fillable = [
        'order_id',
        'attachment',
        'status',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'attachment' => 'array',
        'status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    
}


