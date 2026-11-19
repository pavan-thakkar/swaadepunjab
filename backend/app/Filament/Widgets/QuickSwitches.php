<?php

namespace App\Filament\Widgets;

use App\Models\MenuItem;
use App\Models\ReceiptSetting;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

/** Delivery ON/OFF, loyalty ON/OFF and a quick "mark dish sold out" search (all roles; loyalty: owner). */
class QuickSwitches extends Widget
{
    protected static string $view = 'filament.widgets.quick-switches';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 1;

    public string $dishSearch = '';

    public function toggleDelivery(): void
    {
        $s = ReceiptSetting::getSettings();
        $s->update(['delivery_enabled' => ! $s->delivery_enabled]);
        Notification::make()->title($s->delivery_enabled ? 'Home delivery is ON' : 'Home delivery is OFF')->success()->send();
    }

    public function toggleLoyalty(): void
    {
        if (! auth()->user()?->isOwner()) {
            return;
        }
        $s = ReceiptSetting::getSettings();
        $s->update(['loyalty_enabled' => ! $s->loyalty_enabled]);
        Notification::make()->title($s->loyalty_enabled ? 'Loyalty points ON' : 'Loyalty points OFF')->success()->send();
    }

    public function toggleMonthlyPlans(): void
    {
        if (! auth()->user()?->hasRole(['owner', 'manager'])) {
            return;
        }
        $on = ! \App\Models\MonthlyPlan::sectionEnabled();
        \App\Models\MonthlyPlan::setSectionEnabled($on);
        Notification::make()->title($on ? 'Monthly Plans are showing on the website' : 'Monthly Plans hidden from the website')->success()->send();
    }

    public function setAvailable(int $id, bool $available): void
    {
        $dish = MenuItem::find($id);
        if ($dish) {
            $dish->update(['is_available' => $available]);
            Notification::make()->title($dish->name . ($available ? ' is available again' : ' marked sold out'))->success()->send();
        }
        $this->dishSearch = '';
    }

    protected function getViewData(): array
    {
        $s = ReceiptSetting::getSettings();
        $status = ReceiptSetting::deliveryStatus();
        $results = [];
        $q = trim($this->dishSearch);
        if (mb_strlen($q) >= 2) {
            $results = MenuItem::where('name', 'like', '%' . $q . '%')->orderBy('name')->limit(6)->get(['id', 'name', 'is_available']);
        }
        return [
            'deliveryOn' => (bool) $s->delivery_enabled,
            'deliveryHint' => $s->delivery_enabled
                ? ($status['hours'] ? ($status['available'] ? 'Open · ' . $status['hours'] : 'Closed now · hours ' . $status['hours']) : 'Delivering now')
                : 'Customers can only order Take Away / Dine In',
            'loyaltyOn' => (bool) ($s->loyalty_enabled ?? false),
            'isOwner' => (bool) auth()->user()?->isOwner(),
            'canPlans' => (bool) auth()->user()?->hasRole(['owner', 'manager']),
            'plansOn' => \App\Models\MonthlyPlan::sectionEnabled(),
            'plansHint' => (function () {
                $n = \App\Models\MonthlyPlan::where('is_active', true)->count();
                $new = \App\Models\PlanEnquiry::where('status', 'new')->count();
                return $n . ' plan' . ($n === 1 ? '' : 's') . ($new ? " · {$new} new enquir" . ($new === 1 ? 'y' : 'ies') : '');
            })(),
            'results' => $results,
            'soldOut' => MenuItem::where('is_available', false)->orderBy('name')->limit(12)->get(['id', 'name']),
        ];
    }
}
