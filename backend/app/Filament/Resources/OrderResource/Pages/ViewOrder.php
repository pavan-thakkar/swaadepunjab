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
            Actions\Action::make('check_payment')
                ->label('Check payment with Razorpay')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn () => $this->record->payment_method === 'razorpay' && $this->record->payment_status !== 'paid' && filled($this->record->razorpay_order_id))
                ->action(function () {
                    $r = \App\Services\Razorpay::syncFromRazorpayOrder($this->record);
                    $this->record->refresh();
                    \Filament\Notifications\Notification::make()
                        ->title(match ($r) {
                            'paid'    => 'Paid ✅ — order sent to the kitchen',
                            'pending' => 'Razorpay has no successful payment for this order yet',
                            'failed'  => 'The customer\'s payment failed',
                            default   => 'Could not reach Razorpay — check the keys in Store & Receipt Config',
                        })
                        ->{$r === 'paid' ? 'success' : ($r === 'error' ? 'danger' : 'warning')}()
                        ->send();
                }),

            Actions\Action::make('refund')
                ->label('Refund via Razorpay')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn () => \App\Filament\Support\RefundForm::canRefund($this->record))
                ->modalHeading('Refund money to the customer')
                ->modalDescription('Money goes back to the customer\'s UPI / card / bank in 5–7 working days. This cannot be undone.')
                ->modalSubmitActionLabel('Refund now')
                ->fillForm(fn () => \App\Filament\Support\RefundForm::fill($this->record))
                ->form(fn () => \App\Filament\Support\RefundForm::schema($this->record))
                ->action(function (array $data) {
                    \App\Filament\Support\RefundForm::handle($this->record, $data);
                    $this->record->refresh();
                }),

            Actions\Action::make('rating_link')
                ->label(fn () => $this->record->order_rating ? 'Rated ' . $this->record->order_rating . '★' : 'Send rating link')
                ->icon('heroicon-o-star')
                ->color(fn () => $this->record->order_rating ? ((int) $this->record->order_rating <= 2 ? 'danger' : 'success') : 'gray')
                ->visible(fn () => $this->record->status === 'delivered' && preg_match('/\d{10}/', (string) $this->record->customer_phone))
                ->modalHeading(fn () => $this->record->order_rating ? 'Customer rating' : 'Ask the customer to rate this order')
                ->modalContent(fn () => view('filament.rating-link', ['order' => $this->record]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close'),

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
