<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    public const SWITCH_TYPES = [
        'linear',
        'tactile',
        'clicky',
        'silent',
    ];

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'stock',
        'reserved_quantity',
        'image',
        'is_active',
        'is_homepage_featured',
        'switch_color',
        'switch_type',
        'switch_sound_paths',
        'keycap_texture_uv',
        'keyboard_texture_uv',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
            'reserved_quantity' => 'integer',
            'is_active' => 'boolean',
            'is_homepage_featured' => 'boolean',
            'switch_sound_paths' => 'array',
        ];
    }

    /**
     * A product belongs to a category.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * A product has many order items.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
