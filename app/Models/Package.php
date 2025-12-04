<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $table = 'packages';
    protected $fillable = [
        'title',
        'description',
        'price',
        'explore_link',
        'specification_text',
        'icon_images',
        'status',
    ];
    protected $casts = [
        'title' => 'string',
        'description' => 'string',
        'price' => 'decimal:2',
        'explore_link' => 'string',
        'specification_text' => 'string',
        'icon_images' => 'array',
        'status' => 'string',
    ];

    //  public function getIconImagesAttribute($value): string|null
    // {
    //     if (filter_var($value, FILTER_VALIDATE_URL)) {
    //         return $value;
    //     }
    //     // Check if the request is an API request
    //     if (request()->is('api/*') && !empty($value)) {
    //         // Return the full URL for API requests
    //         return url($value);
    //     }

    //     // Return only the path for web requests
    //     return $value;
    // }
}
