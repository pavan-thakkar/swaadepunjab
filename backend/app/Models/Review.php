<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'menu_item_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'rating',
        'comment',
        'is_approved',
        'admin_reply',
    ];

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
