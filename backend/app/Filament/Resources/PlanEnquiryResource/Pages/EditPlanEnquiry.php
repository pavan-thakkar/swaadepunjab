<?php

namespace App\Filament\Resources\PlanEnquiryResource\Pages;

use App\Filament\Resources\PlanEnquiryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlanEnquiry extends EditRecord
{
    protected static string $resource = PlanEnquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('call')->label('Call')->icon('heroicon-o-phone')->color('gray')
                ->url(fn () => 'tel:' . $this->record->phone),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
