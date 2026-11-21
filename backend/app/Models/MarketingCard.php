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
        'video_path',
        'video_sound',
        'image_path',
        'is_active',
        'sort_order',
        'time_limited',
        'available_from',
        'available_until',
    ];

    protected $appends = ['schedule'];

    /** For the website: show only between these hours (null = all day). */
    public function getScheduleAttribute(): ?array
    {
        if (! $this->time_limited || ! $this->available_from || ! $this->available_until) {
            return null;
        }
        return [
            'from'  => $this->available_from,
            'until' => $this->available_until,
            'label' => \App\Support\TimeWindow::label($this->available_from, $this->available_until),
            'now'   => \App\Support\TimeWindow::isOpen($this->available_from, $this->available_until),
        ];
    }

    protected $casts = [
        'is_active'  => 'boolean',
        'video_sound' => 'boolean',
        'time_limited' => 'boolean',
        'sort_order' => 'integer',
        'menu_item_id' => 'integer',
    ];

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }
}
