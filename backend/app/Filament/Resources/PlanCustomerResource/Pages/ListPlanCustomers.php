<?php

namespace App\Filament\Resources\PlanCustomerResource\Pages;

use App\Filament\Resources\PlanCustomerResource;
use App\Models\PlanEnquiry;
use App\Models\SiteSetting;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPlanCustomers extends ListRecords
{
    protected static string $resource = PlanCustomerResource::class;

    public function getSubheading(): ?string
    {
        $active = PlanEnquiry::where('status', 'active')->count();
        $soon = PlanEnquiry::query()->endingSoon()->count();
        $days = PlanEnquiry::alertDays();
        return "{$active} active · {$soon} ending in the next {$days} days or just ended. Soonest ending at the top.";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('alertDays')
                ->label('Alert ' . PlanEnquiry::alertDays() . ' days before end')
                ->icon('heroicon-o-bell-alert')
                ->color('gray')
                ->fillForm(fn () => ['days' => PlanEnquiry::alertDays()])
                ->form([
                    Forms\Components\TextInput::make('days')
                        ->label('Show the renewal alert this many days before a plan ends')
                        ->numeric()->minValue(1)->maxValue(15)->required()->suffix('days'),
                ])
                ->action(function (array $data) {
                    SiteSetting::setValue('plan_alert_days', (string) (int) $data['days']);
                    Notification::make()->title('Alert setting saved')->success()->send();
                    $this->redirect(PlanCustomerResource::getUrl('index'));
                }),
        ];
    }
}
