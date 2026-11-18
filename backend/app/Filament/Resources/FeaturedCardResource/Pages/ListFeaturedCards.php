<?php

namespace App\Filament\Resources\FeaturedCardResource\Pages;

use App\Filament\Resources\FeaturedCardResource;
use App\Models\SiteSetting;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListFeaturedCards extends ListRecords
{
    protected static string $resource = FeaturedCardResource::class;

    public function getSubheading(): ?string
    {
        return 'Section title on website: "' . SiteSetting::getValue('featured_section_title', "What's New") . '". Drag rows to change the order.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sectionTitle')
                ->label('Edit Section Title')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->fillForm(fn () => [
                    'title'    => SiteSetting::getValue('featured_section_title', "What's New"),
                    'subtitle' => SiteSetting::getValue('featured_section_subtitle'),
                ])
                ->form([
                    Forms\Components\TextInput::make('title')->label('Section Title')->required()->maxLength(60),
                    Forms\Components\TextInput::make('subtitle')->label('Small line under title (optional)')->maxLength(120),
                ])
                ->action(function (array $data) {
                    SiteSetting::setValue('featured_section_title', $data['title']);
                    SiteSetting::setValue('featured_section_subtitle', $data['subtitle'] ?? null);
                    Notification::make()->title('Section title saved')->success()->send();
                }),
            Actions\CreateAction::make()->label('Add Card'),
        ];
    }
}
