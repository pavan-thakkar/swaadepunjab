<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanEnquiry extends Model
{
    public const STATUSES = [
        'new'       => 'New',
        'contacted' => 'Contacted',
        'active'    => 'Active customer',
        'closed'    => 'Closed / not interested',
    ];

    protected $fillable = [
        'monthly_plan_id', 'plan_name', 'name', 'company', 'phone', 'email', 'people',
        'start_date', 'address', 'message', 'status', 'admin_notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'people'     => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MonthlyPlan::class, 'monthly_plan_id');
    }
}
