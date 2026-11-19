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

    public const PAYMENT = [
        'pending' => 'Payment pending',
        'partial' => 'Part paid',
        'paid'    => 'Paid',
    ];

    protected $fillable = [
        'access_token', 'monthly_plan_id', 'plan_name', 'name', 'company', 'phone', 'email', 'people',
        'start_date', 'address', 'message', 'status', 'admin_notes',
        'active_from', 'active_until', 'agreed_people', 'agreed_price', 'amount_paid',
        'payment_status', 'delivery_time', 'customer_note', 'renewal_called_at', 'renewal_called_by',
    ];

    protected $hidden = ['access_token', 'admin_notes'];

    protected $casts = [
        'start_date'    => 'date',
        'active_from'   => 'date',
        'active_until'  => 'date',
        'people'        => 'integer',
        'agreed_people' => 'integer',
        'agreed_price'  => 'decimal:2',
        'amount_paid'   => 'decimal:2',
        'renewal_called_at' => 'datetime',
    ];

    /** Alert this many days before a plan ends (admin can change it). */
    public static function alertDays(): int
    {
        return max(1, min(15, (int) (SiteSetting::getValue('plan_alert_days', '3') ?: 3)));
    }

    /** Active plans ending within the alert window, or ended in the last 7 days without renewal. */
    public function scopeEndingSoon($q)
    {
        return $q->where('status', 'active')
            ->whereNotNull('active_until')
            ->whereDate('active_until', '<=', now()->addDays(self::alertDays() - 1)->toDateString())
            ->whereDate('active_until', '>=', now()->subDays(7)->toDateString());
    }

    /** Ending-soon customers nobody has called yet. */
    public static function needsCallCount(): int
    {
        return static::query()->endingSoon()->whereNull('renewal_called_at')->count();
    }

    public function whatsappUrl(bool $renewal = true): string
    {
        $num = substr(preg_replace('/\D/', '', (string) $this->phone), -10);
        $plan = $this->plan_name ?: 'monthly';
        $text = $renewal && $this->active_until
            ? ($this->active_until->lt(now()->startOfDay())
                ? "Namaste {$this->name}, your {$plan} plan with Swaad E Punjab ended on {$this->active_until->format('d M')}. Shall we continue it for next month?"
                : "Namaste {$this->name}, your {$plan} plan with Swaad E Punjab ends on {$this->active_until->format('d M')}. Shall we continue it for next month?")
            : "Namaste {$this->name}, thank you for your interest in our {$plan} plan at Swaad E Punjab.";
        return 'https://wa.me/91' . $num . '?text=' . rawurlencode($text);
    }

    /** "Ends today", "2 days left", "Ended 3 days ago" */
    public function endsLabel(): ?string
    {
        if (! $this->active_until) return null;
        $d = $this->daysLeft();
        return match (true) {
            $d > 1  => $d . ' days left',
            $d === 1 => 'Ends today',
            $d === 0 => 'Ended yesterday',
            default => 'Ended ' . abs($d - 1) . ' days ago',
        };
    }

    /** Days left including today (null if no end date). */
    public function daysLeft(): ?int
    {
        if (! $this->active_until) return null;
        return (int) now()->startOfDay()->diffInDays($this->active_until->copy()->startOfDay(), false) + 1;
    }

    /** Where the customer's plan stands right now. */
    public function stage(): string
    {
        if ($this->status === 'closed') return 'closed';
        if ($this->status !== 'active') return 'requested';
        $today = now()->startOfDay();
        if ($this->active_from && $this->active_from->gt($today)) return 'upcoming';
        if ($this->active_until && $this->active_until->lt($today)) return 'ended';
        return 'active';
    }

    /** What the customer sees on the website (no admin notes). */
    public function toCustomerArray(): array
    {
        $total = ($this->active_from && $this->active_until)
            ? (int) $this->active_from->copy()->startOfDay()->diffInDays($this->active_until->copy()->startOfDay()) + 1
            : null;
        $left = $this->daysLeft();
        $price = $this->agreed_price !== null ? (float) $this->agreed_price : null;
        $paid = (float) $this->amount_paid;

        return [
            'id'             => $this->id,
            'stage'          => $this->stage(),
            'plan_name'      => $this->plan_name ?: ($this->plan?->name ?? 'Monthly plan'),
            'meal_label'     => $this->plan ? (MonthlyPlan::MEAL_TYPES[$this->plan->meal_type] ?? null) : null,
            'company'        => $this->company,
            'people'         => $this->agreed_people ?: $this->people,
            'active_from'    => $this->active_from?->toDateString(),
            'active_until'   => $this->active_until?->toDateString(),
            'total_days'     => $total,
            'days_left'      => $left !== null ? max(0, min($left, $total ?? $left)) : null,
            'price'          => $price,
            'amount_paid'    => $paid,
            'balance'        => $price !== null ? max(0, round($price - $paid, 2)) : null,
            'payment_status' => $this->payment_status,
            'payment_label'  => self::PAYMENT[$this->payment_status] ?? $this->payment_status,
            'delivery_time'  => $this->delivery_time,
            'note'           => $this->customer_note,
            'requested_at'   => $this->created_at?->toIso8601String(),
            // What the customer sent
            'request'        => [
                'name'       => $this->name,
                'company'    => $this->company,
                'phone'      => $this->phone,
                'email'      => $this->email,
                'people'     => $this->people,
                'start_date' => $this->start_date?->toDateString(),
                'address'    => $this->address,
                'message'    => $this->message,
            ],
            'status_label'   => self::STATUSES[$this->status] ?? $this->status,
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MonthlyPlan::class, 'monthly_plan_id');
    }
}
