<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'orderable_id',
        'orderable_type',
        'user_id',
        'order_number',
        'progress',
        'start_date',
        'end_date',
        'amount',
        'coupon_id',
        'original_amount',
        'discount_amount',
        'payment_status',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'orderable_id' => 'integer',
        'orderable_type' => 'string',
        'user_id' => 'integer',
        'order_number' => 'string',
        'progress' => 'integer',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'amount' => 'decimal:2',
        'coupon_id' => 'integer',
        'original_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'payment_status' => 'string',
        'status' => 'string',
    ];

    /**
     * Get the user that owns the order.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the addons for this order.
     */
    public function addons()
    {
        return $this->hasMany(OrderAddon::class);
    }

    /**
     * Get the detail record for this order.
     */
    public function detail()
    {
        return $this->hasOne(OrderDetail::class);
    }

    /**
     * Get the invoices for this order.
     */
    public function invoices()
    {
        return $this->morphMany(Invoice::class, 'invoiceable');
    }

    /**
     * Get all of the order's payments.
     */
    public function payments()
    {
        return $this->morphMany(Payment::class, 'paymentable');
    }

    public function orderable()
    {
        return $this->morphTo();
    }

    public function delivery()
    {
        return $this->hasOne(OrderDelivery::class);
    }

    /**
     * Get the coupon used for this order.
     */
    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }
}


