<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print_bill')
                ->label('Print Receipt')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->modalContent(fn () => view('admin.order-bill', ['order' => $this->record]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close'),

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
                    
                    // Notify user
                    \Filament\Notifications\Notification::make()
                        ->title('Order Accepted')
                        ->body("Preparation time set to {$data['prep_time_minutes']} minutes.")
                        ->success()
                        ->send();
                }),

            Actions\Action::make('ready_order')
                ->label('Ready')
                ->icon('heroicon-o-check-circle')
                ->color('warning')
                ->visible(fn () => $this->record->status === 'accepted')
                ->action(function () {
                    $this->record->update(['status' => 'preparing']);
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Order Ready')
                        ->body('Order is now prepared and ready.')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('dispatch_order')
                ->label('Dispatch')
                ->icon('heroicon-o-truck')
                ->color('info')
                ->visible(fn () => $this->record->status === 'preparing')
                ->action(function () {
                    $this->record->update(['status' => 'out_for_delivery']);
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Order Dispatched')
                        ->body('Order is out for delivery.')
                        ->info()
                        ->send();
                }),

            Actions\Action::make('deliver_order')
                ->label('Delivered')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () => $this->record->status === 'out_for_delivery')
                ->action(function () {
                    $this->record->update(['status' => 'delivered']);
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Order Delivered')
                        ->body('Order marked as delivered successfully.')
                        ->success()
                        ->send();
                }),

            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
