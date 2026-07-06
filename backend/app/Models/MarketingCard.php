<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingCard extends Model
{
    protected $fillable = [
        'tag',
        'title',
        'description',
        'price',
        'menu_item_id',
        'video_url',
        'image_path',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
        'menu_item_id' => 'integer',
    ];

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }
}
