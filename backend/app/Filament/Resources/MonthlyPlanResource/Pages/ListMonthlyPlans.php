<?php

namespace App\Filament\Resources\MonthlyPlanResource\Pages;

use App\Filament\Resources\MonthlyPlanResource;
use App\Models\MonthlyPlan;
use App\Models\SiteSetting;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListMonthlyPlans extends ListRecords
{
    protected static string $resource = MonthlyPlanResource::class;

    public function getSubheading(): ?string
    {
        $on = MonthlyPlan::sectionEnabled();
        $count = MonthlyPlan::where('is_active', true)->count();
        if (! $on) {
            return 'Monthly Plans are HIDDEN on the website. Turn them on with the button on the right.';
        }
        return $count
            ? "Monthly Plans are LIVE on the website ({$count} plan" . ($count === 1 ? '' : 's') . ' shown). Drag rows to change the order.'
            : 'Monthly Plans are ON, but no plan is set to show yet — add or switch on a plan.';
    }

    protected function getHeaderActions(): array
    {
        $on = MonthlyPlan::sectionEnabled();

        return [
            Actions\Action::make('toggleSection')
                ->label($on ? 'Showing on website · Turn OFF' : 'Hidden on website · Turn ON')
                ->icon($on ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                ->color($on ? 'gray' : 'success')
                ->requiresConfirmation()
                ->modalHeading($on ? 'Hide Monthly Plans from the website?' : 'Show Monthly Plans on the website?')
                ->modalDescription($on
                    ? 'Customers will no longer see the plans or be able to send enquiries.'
                    : 'Customers will see your active plans on the home page and on the Monthly Plans page.')
                ->action(function () use ($on) {
                    MonthlyPlan::setSectionEnabled(! $on);
                    Notification::make()->title($on ? 'Monthly Plans hidden' : 'Monthly Plans are now live')->success()->send();
                    $this->redirect(MonthlyPlanResource::getUrl('index'));
                }),
            Actions\Action::make('sectionText')
                ->label('Edit heading')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->fillForm(fn () => [
                    'title'    => MonthlyPlan::sectionTitle(),
                    'subtitle' => MonthlyPlan::sectionSubtitle(),
                ])
                ->form([
                    Forms\Components\TextInput::make('title')->label('Heading')->required()->maxLength(60),
                    Forms\Components\TextInput::make('subtitle')->label('Line under heading')->maxLength(160),
                ])
                ->action(function (array $data) {
                    SiteSetting::setValue('monthly_plans_title', $data['title']);
                    SiteSetting::setValue('monthly_plans_subtitle', $data['subtitle'] ?? null);
                    Notification::make()->title('Heading saved')->success()->send();
                }),
            Actions\CreateAction::make()->label('New plan'),
        ];
    }
}
