<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One day of a monthly plan: delivered or skipped. */
class PlanDelivery extends Model
{
    protected $fillable = ['plan_enquiry_id', 'date', 'people', 'status', 'note', 'done_by'];

    protected $casts = ['date' => 'date', 'people' => 'integer'];

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(PlanEnquiry::class, 'plan_enquiry_id');
    }
}
