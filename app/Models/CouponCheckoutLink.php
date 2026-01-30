<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponCheckoutLink extends Model
{
    protected $fillable = [
        'coupon_id',
        'user_id',
        'package_id',
        'addon_id',
        'checkout_link',
    ];

    /**
     * Get the coupon.
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Get the user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the package.
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Get the addon.
     */
    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }
}
