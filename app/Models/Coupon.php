<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'type',
        'value',
        'min_order_value',
        'usage_limit',
        'is_unlimited_time',
        'start_date',
        'start_time',
        'end_date',
        'end_time',
        'status',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_order_value' => 'decimal:2',
        'usage_limit' => 'integer',
        'is_unlimited_time' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Get the users assigned to this coupon.
     */
    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('checkout_link')->withTimestamps();
    }

    /**
     * Get the usages of this coupon.
     */
    public function usages()
    {
        return $this->hasMany(CouponUsage::class);
    }

    /**
     * Get the orders that used this coupon.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Safely parse date and time into Carbon instance.
     */
    private function parseDateTime($date, $time): ?Carbon
    {
        if (!$date || !$time) {
            return null;
        }

        try {
            $dateString = $date instanceof Carbon ? $date->format('Y-m-d') : $date;
            $timeString = is_string($time) ? $time : (string) $time;
            
            // Ensure time is in H:i:s format
            if (strlen($timeString) === 5) {
                $timeString .= ':00'; // Add seconds if missing
            }
            
            return Carbon::parse($dateString . ' ' . $timeString, 'Asia/Kuala_Lumpur');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if coupon is currently valid.
     */
    public function isValid(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        // Check time validity
        if (!$this->is_unlimited_time) {
            $startDateTime = $this->parseDateTime($this->start_date, $this->start_time);
            $endDateTime = $this->parseDateTime($this->end_date, $this->end_time);

            if (!$startDateTime || !$endDateTime) {
                return false;
            }

            $now = Carbon::now('Asia/Kuala_Lumpur');
            if ($now->lt($startDateTime) || $now->gt($endDateTime)) {
                return false;
            }
        }

        // Check usage limit
        if ($this->usage_limit !== null) {
            $usageCount = $this->usages()->count();
            if ($usageCount >= $this->usage_limit) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if coupon can be applied to the given order amount.
     * Coupon value must not exceed order value (for fixed/amount type).
     */
    public function canBeAppliedTo(float $orderAmount): bool
    {
        if ($this->type !== 'percentage' && (float) $this->value > $orderAmount) {
            return false; // Fixed coupon value cannot exceed order amount
        }

        return true;
    }

    /**
     * Calculate discount amount for a given order amount.
     */
    public function calculateDiscount(float $orderAmount): float
    {
        if ($this->type === 'percentage') {
            return round(($orderAmount * $this->value) / 100, 2);
        } else {
            return min($this->value, $orderAmount); // Don't exceed order amount
        }
    }

    /**
     * Get time left until expiry.
     */
    public function getTimeLeftAttribute(): ?string
    {
        if ($this->is_unlimited_time || !$this->end_date || !$this->end_time) {
            return null;
        }

        $endDateTime = $this->parseDateTime($this->end_date, $this->end_time);
        if (!$endDateTime) {
            return null;
        }

        $now = Carbon::now('Asia/Kuala_Lumpur');

        if ($now->gte($endDateTime)) {
            return 'Expired';
        }

        $diff = $now->diff($endDateTime);
        
        $parts = [];
        if ($diff->days > 0) {
            $parts[] = $diff->days . ' day' . ($diff->days > 1 ? 's' : '');
        }
        if ($diff->h > 0) {
            $parts[] = $diff->h . ' hour' . ($diff->h > 1 ? 's' : '');
        }
        if ($diff->i > 0 && count($parts) < 2) {
            $parts[] = $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
        }

        return implode(' ', $parts);
    }

    /**
     * Update coupon status based on current conditions.
     */
    public function updateStatus(): void
    {
        if ($this->status === 'deactivated') {
            return; // Don't auto-update deactivated coupons
        }

        $now = Carbon::now('Asia/Kuala_Lumpur');

        // Check if exhausted
        if ($this->usage_limit !== null) {
            $usageCount = $this->usages()->count();
            if ($usageCount >= $this->usage_limit) {
                $this->status = 'exhausted';
                $this->save();
                return;
            }
        }

        // Check if expired
        if (!$this->is_unlimited_time && $this->end_date && $this->end_time) {
            $endDateTime = $this->parseDateTime($this->end_date, $this->end_time);
            if ($endDateTime && $now->gt($endDateTime)) {
                $this->status = 'expired';
                $this->save();
                return;
            }
        }

        // Check if scheduled (future start date)
        if (!$this->is_unlimited_time && $this->start_date && $this->start_time) {
            $startDateTime = $this->parseDateTime($this->start_date, $this->start_time);
            if ($startDateTime && $now->lt($startDateTime)) {
                $this->status = 'scheduled';
                $this->save();
                return;
            }
        }

        // Otherwise, it's active
        if ($this->status !== 'active') {
            $this->status = 'active';
            $this->save();
        }
    }
}
