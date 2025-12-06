<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Addon extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'sub_title',
        'description',
        'price',
        'status',
    ];

    protected $casts = [
        'title' => 'string',
        'sub_title' => 'string',
        'description' => 'string',
        'price' => 'decimal:2',
        'status' => 'string',
    ];

    /**
     * Get order addons that use this addon.
     */
    public function orderAddons()
    {
        return $this->hasMany(OrderAddon::class);
    }
}


