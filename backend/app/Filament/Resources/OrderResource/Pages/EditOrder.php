<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('accept_order')
                ->label('Accept Order')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->status === 'pending')
                ->form([
                    Forms\Components\TextInput::make('prep_time_minutes')
                        ->label('Preparation Time (minutes)')
                        ->numeric()
                        ->default(20)
                        ->required()
                        ->minValue(13)
                        ->maxValue(120)
                        ->helperText('Minimum preparation time is 13 minutes.'),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'status'                => 'accepted',
                        'prep_time_minutes'     => $data['prep_time_minutes'],
                        'estimated_delivery_at' => now()->addMinutes($data['prep_time_minutes']),
                    ]);
                    
                    $this->fillForm();
                    
                    // Notify user
                    \Filament\Notifications\Notification::make()
                        ->title('Order Accepted')
                        ->body("Preparation time set to {$data['prep_time_minutes']} minutes.")
                        ->success()
                        ->send();
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
