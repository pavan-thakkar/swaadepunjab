<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyTransaction extends Model
{
    protected $fillable = ['phone', 'order_id', 'points', 'type', 'note'];

    protected $casts = ['points' => 'integer'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function setPhoneAttribute($value): void
    {
        $this->attributes['phone'] = \App\Support\CustomerToken::last10($value) ?? $value;
    }
}
