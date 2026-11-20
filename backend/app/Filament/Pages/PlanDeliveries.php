<?php

namespace App\Filament\Pages;

use App\Models\PlanDelivery;
use App\Models\PlanEnquiry;
use App\Models\SiteSetting;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/** Today's monthly-plan meals: who gets how many, when and where. Tick delivered / skip, print the list. */
class PlanDeliveries extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = "Today's Plan Deliveries";
    protected static ?string $navigationGroup = 'Grow';
    protected static ?int $navigationSort = 8;
    protected static ?string $title = 'Plan Deliveries';
    protected static ?string $slug = 'plan-deliveries';
    protected static string $view = 'filament.pages.plan-deliveries';

    public string $date = '';

    /** @var array<int,int|string> people override per plan for the chosen day */
    public array $people = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole(['owner', 'manager', 'staff']);
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            $n = static::plansFor(today())->count();
        } catch (\Throwable $e) {
            return null;
        }
        return $n ? (string) $n : null;
    }

    public function mount(): void
    {
        $d = request()->query('date');
        $this->date = $d && strtotime($d) ? Carbon::parse($d)->toDateString() : today()->toDateString();
    }

    public static function runsOnSunday(): bool
    {
        return SiteSetting::getValue('plan_runs_sunday', '0') === '1';
    }

    /** Active plans that cover this date, earliest delivery time first. */
    public static function plansFor(Carbon $day): Collection
    {
        if ($day->isSunday() && ! static::runsOnSunday()) {
            return collect();
        }
        return PlanEnquiry::with('plan')
            ->where('status', 'active')
            ->whereNotNull('active_from')
            ->whereDate('active_from', '<=', $day)
            ->where(fn ($q) => $q->whereNull('active_until')->orWhereDate('active_until', '>=', $day))
            ->get()
            ->sortBy(fn ($p) => [static::timeSortKey($p->delivery_time), mb_strtolower((string) ($p->company ?: $p->name))])
            ->values();
    }

    /** "12:30 PM", "1 pm", "13:00" → minutes after midnight (unknown times go last). */
    public static function timeSortKey(?string $t): int
    {
        if (! $t || ! ($ts = strtotime($t))) {
            return 24 * 60 + 1;
        }
        return (int) date('G', $ts) * 60 + (int) date('i', $ts);
    }

    public function shift(int $days): void
    {
        $this->date = Carbon::parse($this->date)->addDays($days)->toDateString();
        $this->people = [];
    }

    public function goToday(): void
    {
        $this->date = today()->toDateString();
        $this->people = [];
    }

    public function updatedDate(): void
    {
        $this->people = [];
    }

    public function toggleSunday(): void
    {
        SiteSetting::setValue('plan_runs_sunday', static::runsOnSunday() ? '0' : '1');
        Notification::make()->title(static::runsOnSunday() ? 'Plans now run on Sundays' : 'Sundays are off for plans')->success()->send();
    }

    private function save(int $planId, string $status): void
    {
        $plan = PlanEnquiry::find($planId);
        if (! $plan) {
            return;
        }
        $default = (int) ($plan->agreed_people ?: $plan->people ?: 1);
        $count = isset($this->people[$planId]) && is_numeric($this->people[$planId]) ? max(0, (int) $this->people[$planId]) : $default;

        PlanDelivery::updateOrCreate(
            ['plan_enquiry_id' => $planId, 'date' => $this->date],
            ['status' => $status, 'people' => $status === 'delivered' ? $count : null, 'done_by' => auth()->user()?->name]
        );
    }

    public function markDelivered(int $planId): void
    {
        $this->save($planId, 'delivered');
    }

    public function markSkipped(int $planId): void
    {
        $this->save($planId, 'skipped');
    }

    public function undo(int $planId): void
    {
        PlanDelivery::where('plan_enquiry_id', $planId)->whereDate('date', $this->date)->delete();
    }

    public function markAllDelivered(): void
    {
        $day = Carbon::parse($this->date);
        $done = PlanDelivery::whereDate('date', $day)->pluck('plan_enquiry_id')->all();
        $n = 0;
        foreach (static::plansFor($day) as $p) {
            if (! in_array($p->id, $done, true)) {
                $this->save($p->id, 'delivered');
                $n++;
            }
        }
        Notification::make()->title($n ? "{$n} marked as delivered" : 'Everything is already marked')->success()->send();
    }

    protected function getViewData(): array
    {
        $day = Carbon::parse($this->date);
        $plans = static::plansFor($day);
        $marks = PlanDelivery::whereDate('date', $day)->get()->keyBy('plan_enquiry_id');
        $deliveredDays = PlanDelivery::whereIn('plan_enquiry_id', $plans->pluck('id'))
            ->where('status', 'delivered')
            ->selectRaw('plan_enquiry_id, COUNT(*) as n')->groupBy('plan_enquiry_id')->pluck('n', 'plan_enquiry_id');

        $rows = $plans->map(function (PlanEnquiry $p) use ($marks, $deliveredDays) {
            $mark = $marks->get($p->id);
            $default = (int) ($p->agreed_people ?: $p->people ?: 1);
            return [
                'p'        => $p,
                'meals'    => $mark && $mark->status === 'delivered' && $mark->people !== null ? $mark->people : $default,
                'default'  => $default,
                'mark'     => $mark,
                'days'     => (int) ($deliveredDays[$p->id] ?? 0),
                'planDays' => (int) ($p->plan?->days_per_month ?: 0),
                'endsSoon' => $p->active_until && $p->active_until->diffInDays($day, false) >= -2,
            ];
        });

        $groups = $rows->groupBy(fn ($r) => trim((string) $r['p']->delivery_time) ?: 'Time not set');

        return [
            'day'       => $day,
            'isToday'   => $day->isToday(),
            'isSunday'  => $day->isSunday(),
            'sundayOn'  => static::runsOnSunday(),
            'groups'    => $groups,
            'customers' => $rows->count(),
            'meals'     => $rows->where('mark.status', '!=', 'skipped')->sum('meals'),
            'delivered' => $rows->filter(fn ($r) => $r['mark']?->status === 'delivered')->count(),
            'skipped'   => $rows->filter(fn ($r) => $r['mark']?->status === 'skipped')->count(),
            'printUrl'  => route('admin.plan-deliveries.print', ['date' => $day->toDateString()]),
        ];
    }
}
