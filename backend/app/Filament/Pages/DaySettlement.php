<?php

namespace App\Filament\Pages;

use App\Models\DayClosing;
use App\Models\Order;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Hisab: everything collected in one business day, by Cash / UPI / Card / Online,
 * the bills nobody settled yet, who collected what — and the cash count that must match the drawer.
 */
class DaySettlement extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = 'Hisab';
    protected static ?string $navigationGroup = 'Live';
    protected static ?int $navigationSort = 3;
    protected static ?string $title = 'Hisab';
    protected static ?string $slug = 'hisab';
    protected static string $view = 'filament.pages.day-settlement';

    /** A business day runs from 4:00 AM to 3:59 AM next morning, so bills after midnight count in tonight's hisab. */
    public const DAY_START_HOUR = 4;

    public string $date = '';
    public $opening_cash = 0;
    public $expenses = 0;
    public $counted_cash = null;
    public $upi_counted = null;
    public $card_counted = null;
    public string $note = '';
    public bool $editing = false;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole(['owner', 'manager', 'staff']);
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            $n = Order::query()->unsettled()->count();
        } catch (\Throwable $e) {
            return null;
        }
        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function businessToday(): Carbon
    {
        $now = now();
        return ($now->hour < self::DAY_START_HOUR ? $now->copy()->subDay() : $now->copy())->startOfDay();
    }

    /** [start, end) of a business day, in the app timezone. */
    public static function window(Carbon $day): array
    {
        $start = $day->copy()->startOfDay()->setTime(self::DAY_START_HOUR, 0);
        return [$start, $start->copy()->addDay()];
    }

    /** All the numbers for one business day (used by the page and the printed slip). */
    public static function summary(Carbon $day): array
    {
        [$start, $end] = self::window($day);
        $settled = Order::whereNotNull('settle_mode')->where('settled_at', '>=', $start)->where('settled_at', '<', $end)
            ->orderBy('settled_at')->get();
        $online = Order::whereNull('settle_mode')->where('payment_method', 'razorpay')->whereIn('payment_status', ['paid', 'refunded'])
            ->where(fn ($q) => $q->where(fn ($w) => $w->whereNotNull('paid_at')->where('paid_at', '>=', $start)->where('paid_at', '<', $end))
                ->orWhere(fn ($w) => $w->whereNull('paid_at')->where('created_at', '>=', $start)->where('created_at', '<', $end)))
            ->orderBy('created_at')->get();

        $t = ['cash' => 0.0, 'upi' => 0.0, 'card' => 0.0, 'online' => 0.0];
        $staff = [];
        foreach ($settled->concat($online) as $o) {
            $sp = $o->settledSplit();
            foreach ($sp as $k => $v) $t[$k] += $v;
            $who = $o->settle_mode ? ($o->settled_by_name ?: '—') : 'Website (Razorpay)';
            $staff[$who] ??= ['bills' => 0, 'cash' => 0.0, 'upi' => 0.0, 'card' => 0.0, 'online' => 0.0];
            $staff[$who]['bills']++;
            foreach ($sp as $k => $v) $staff[$who][$k] += $v;
        }
        $t = array_map(fn ($v) => round($v, 2), $t);
        $collected = round(array_sum($t), 2);

        $refunds = (float) Order::whereNotNull('refunded_at')->where('refunded_at', '>=', $start)->where('refunded_at', '<', $end)->sum('refund_amount');
        $unsettled = Order::query()->unsettled()->where('created_at', '>=', $start)->where('created_at', '<', $end)->orderBy('created_at')->get();
        $olderUnsettled = Order::query()->unsettled()->where('created_at', '<', $start)->count();
        $cancelled = Order::where('status', 'cancelled')->where('created_at', '>=', $start)->where('created_at', '<', $end)->count();

        return [
            'start' => $start, 'end' => $end, 'totals' => $t, 'collected' => $collected,
            'bills' => $settled->count() + $online->count(), 'settled' => $settled, 'online' => $online,
            'staff' => $staff, 'refunds' => round($refunds, 2), 'unsettled' => $unsettled,
            'unsettled_amount' => round((float) $unsettled->sum('total'), 2), 'older_unsettled' => $olderUnsettled, 'cancelled' => $cancelled,
        ];
    }

    public function mount(): void
    {
        $d = request()->query('date');
        $this->date = $d && strtotime($d) ? Carbon::parse($d)->toDateString() : self::businessToday()->toDateString();
        $this->loadClosing();
    }

    public function updatedDate(): void
    {
        $this->loadClosing();
    }

    protected function loadClosing(): void
    {
        $c = DayClosing::whereDate('business_date', $this->date)->first();
        $this->editing = false;
        if ($c) {
            $this->opening_cash = (float) $c->opening_cash; $this->expenses = (float) $c->expenses; $this->counted_cash = (float) $c->counted_cash;
            $this->upi_counted = $c->upi_counted !== null ? (float) $c->upi_counted : null;
            $this->card_counted = $c->card_counted !== null ? (float) $c->card_counted : null;
            $this->note = (string) $c->note;
        } else {
            // Opening cash defaults to what was counted at last night's close
            $prev = DayClosing::whereDate('business_date', '<', $this->date)->orderByDesc('business_date')->first();
            $this->opening_cash = 0; $this->expenses = 0; $this->counted_cash = null; $this->upi_counted = null; $this->card_counted = null; $this->note = '';
            $this->opening_cash = $prev ? (float) $prev->counted_cash : 0;
        }
    }

    public function shift(int $days): void
    {
        $this->date = Carbon::parse($this->date)->addDays($days)->toDateString();
        $this->loadClosing();
    }

    public function goToday(): void
    {
        $this->date = self::businessToday()->toDateString();
        $this->loadClosing();
    }

    public function quickSettle(int $id, string $mode): void
    {
        if (! in_array($mode, ['cash', 'upi', 'card'], true)) return;
        $o = Order::find($id);
        if (! $o || ! $o->needsSettlement()) {
            Notification::make()->title('Already settled')->warning()->send();
            return;
        }
        $o->settle($mode);
        Notification::make()->title("{$o->order_number} settled — " . $o->settleLabel())->success()->send();
    }

    public function canReclose(): bool
    {
        return (bool) auth()->user()?->hasRole(['owner', 'manager']);
    }

    public function startEdit(): void
    {
        if ($this->canReclose()) $this->editing = true;
    }

    public function closeDay(): void
    {
        $exists = DayClosing::whereDate('business_date', $this->date)->exists();
        if ($exists && ! $this->canReclose()) {
            Notification::make()->title('This day is already closed')->body('Ask the owner or manager to change it.')->warning()->send();
            return;
        }
        $this->validate([
            'opening_cash' => 'nullable|numeric|min:0', 'expenses' => 'nullable|numeric|min:0',
            'counted_cash' => 'required|numeric|min:0', 'upi_counted' => 'nullable|numeric|min:0', 'card_counted' => 'nullable|numeric|min:0',
        ], ['counted_cash.required' => 'Count the cash in the drawer and type it here.']);

        $s = self::summary(Carbon::parse($this->date));
        $expected = round((float) $this->opening_cash + $s['totals']['cash'] - (float) $this->expenses, 2);
        $diff = round((float) $this->counted_cash - $expected, 2);
        $user = auth()->user();
        DayClosing::updateOrCreate(['business_date' => $this->date], [
            'opening_cash' => (float) $this->opening_cash, 'expenses' => (float) $this->expenses,
            'expected_cash' => $expected, 'counted_cash' => (float) $this->counted_cash, 'cash_difference' => $diff,
            'upi_counted' => $this->upi_counted === null || $this->upi_counted === '' ? null : (float) $this->upi_counted,
            'card_counted' => $this->card_counted === null || $this->card_counted === '' ? null : (float) $this->card_counted,
            'totals' => $s['totals'] + ['collected' => $s['collected'], 'bills' => $s['bills'], 'refunds' => $s['refunds'], 'unsettled_count' => $s['unsettled']->count(), 'unsettled_amount' => $s['unsettled_amount']],
            'note' => trim($this->note) ?: null, 'closed_by' => $user?->id, 'closed_by_name' => $user?->name,
        ]);
        \App\Models\ActivityLog::record('day_close', 'Closed hisab for ' . Carbon::parse($this->date)->format('d M Y') . ': cash expected ₹' . number_format($expected, 2)
            . ', counted ₹' . number_format((float) $this->counted_cash, 2) . ($diff == 0.0 ? ' (matched)' : ($diff > 0 ? ' (excess ₹' . number_format($diff, 2) . ')' : ' (short ₹' . number_format(-$diff, 2) . ')')));
        $this->editing = false;
        Notification::make()->title($diff == 0.0 ? 'Hisab matched ✅ — day closed' : ($diff > 0 ? 'Day closed — cash excess ₹' . number_format($diff, 2) : 'Day closed — cash short ₹' . number_format(-$diff, 2)))
            ->{$diff == 0.0 ? 'success' : 'warning'}()->send();
    }

    protected function getViewData(): array
    {
        $day = Carbon::parse($this->date);
        $s = self::summary($day);
        $closing = DayClosing::whereDate('business_date', $this->date)->first();
        $expected = round((float) $this->opening_cash + $s['totals']['cash'] - (float) $this->expenses, 2);
        $hasCount = $this->counted_cash !== null && $this->counted_cash !== '';
        return $s + [
            'day' => $day, 'isToday' => $day->isSameDay(self::businessToday()), 'closing' => $closing,
            'expected' => $expected, 'diff' => $hasCount ? round((float) $this->counted_cash - $expected, 2) : null,
            'upiDiff' => ($this->upi_counted !== null && $this->upi_counted !== '') ? round((float) $this->upi_counted - $s['totals']['upi'], 2) : null,
            'cardDiff' => ($this->card_counted !== null && $this->card_counted !== '') ? round((float) $this->card_counted - $s['totals']['card'], 2) : null,
            'locked' => $closing && ! $this->editing,
            'printUrl' => route('admin.hisab.print', ['date' => $this->date]),
        ];
    }
}
