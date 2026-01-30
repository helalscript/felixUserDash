<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

class OrderAddon extends Model
{
    use HasFactory;

    protected $table = 'order_addons';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'addon_id',
        'user_id',
        'name',
        'sub_title',
        'description',
        'amount',
        'coupon_id',
        'coupon_code',
        'coupon_value',
        'original_amount',
        'discount_amount',
        'status',
        'payment_status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'order_id' => 'integer',
        'addon_id' => 'integer',
        'user_id' => 'integer',
        'name' => 'string',
        'sub_title' => 'string',
        'description' => 'string',
        'amount' => 'decimal:2',
        'coupon_id' => 'integer',
        'coupon_value' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'status' => 'string',
        'payment_status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function addon()
    {
        return $this->belongsTo(Addon::class);
    }

    /**
     * Get the coupon used for this order addon.
     */
    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }
    public function payments() {
        return $this->morphMany(Payment::class, 'paymentable');
    }
    
    public function invoices() {
        return $this->morphMany(Invoice::class, 'invoiceable');
    }
}


