<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $table = 'packages';

    protected $fillable = [
        'title',
        'subtitle',
        'description',
        'price',
        'explore_link',
        'specification_text',
        'features',
        'is_popular',
        'position',
        'icon_images',
        'status',
    ];

    protected $casts = [
        'title' => 'string',
        'subtitle' => 'string',
        'description' => 'string',
        'price' => 'decimal:2',
        'explore_link' => 'string',
        'specification_text' => 'string',
        'features' => 'array',
        'is_popular' => 'boolean',
        'position' => 'integer',
        'icon_images' => 'array',
        'status' => 'string',
    ];

    /**
     * Accessor to always return full URLs for icon images.
     *
     * @param  mixed  $value
     * @return array|null
     */
    public function getIconImagesAttribute($value): ?array
    {
        if (is_null($value)) {
            return null;
        }

        // When using 'array' cast, $value is already an array; if not, decode JSON.
        $images = is_array($value) ? $value : json_decode($value, true) ?? [];

        return array_map(function ($image) {
            return filter_var($image, FILTER_VALIDATE_URL) ? $image : url($image);
        }, $images);
    }
}
