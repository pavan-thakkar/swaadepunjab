<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PlanCustomerResource;
use App\Models\PlanEnquiry;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

/** Dashboard alert: monthly plans ending in the next few days — call the customer to continue. */
class PlanRenewals extends Widget
{
    protected static string $view = 'filament.widgets.plan-renewals';
    protected static ?int $sort = 0;
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasRole(['owner', 'manager']);
    }

    public function markCalled(int $id): void
    {
        $r = PlanEnquiry::find($id);
        if (! $r || $r->status !== 'active') return;
        $who = auth()->user()?->name ?? 'Admin';
        $r->update([
            'renewal_called_at' => now(),
            'renewal_called_by' => $who,
            'admin_notes' => trim(($r->admin_notes ? $r->admin_notes . "\n" : '') . now()->format('d M') . " renewal call by {$who}"),
        ]);
        Notification::make()->title("Marked {$r->name} as called")->success()->send();
    }

    protected function getViewData(): array
    {
        $rows = PlanEnquiry::query()->endingSoon()
            ->orderByRaw('CASE WHEN renewal_called_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('active_until')
            ->limit(8)
            ->get();

        return [
            'rows' => $rows,
            'days' => PlanEnquiry::alertDays(),
            'notCalled' => $rows->whereNull('renewal_called_at')->count(),
            'url' => PlanCustomerResource::getUrl('index'),
        ];
    }
}
