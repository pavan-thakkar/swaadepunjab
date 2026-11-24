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
            Actions\ActionGroup::make([
                Actions\Action::make('print_kot')->label('KOT (kitchen)')->icon('heroicon-o-fire')
                    ->url(fn () => route('admin.order.print', ['order' => $this->record->id, 'type' => 'kot']), shouldOpenInNewTab: true),
                Actions\Action::make('print_bill')->label('Bill')->icon('heroicon-o-document-text')
                    ->url(fn () => route('admin.order.print', ['order' => $this->record->id, 'type' => 'bill']), shouldOpenInNewTab: true),
                Actions\Action::make('print_both')->label('KOT + Bill')->icon('heroicon-o-document-duplicate')
                    ->url(fn () => route('admin.order.print', ['order' => $this->record->id, 'type' => 'both']), shouldOpenInNewTab: true),
            ])->label('Print')->icon('heroicon-o-printer')->color('gray')->button(),

            Actions\Action::make('settle')
                ->label('Settle bill')
                ->icon('heroicon-o-banknotes')
                ->color('danger')
                ->visible(fn () => $this->record->needsSettlement())
                ->modalHeading('Settle bill')
                ->modalWidth('lg')
                ->modalSubmitActionLabel('Settle bill')
                ->fillForm(fn () => \App\Filament\Support\SettleBill::fill($this->record))
                ->form(fn () => \App\Filament\Support\SettleBill::schema($this->record))
                ->action(function (array $data, Actions\Action $action) {
                    if (! \App\Filament\Support\SettleBill::apply($this->record, $data)) $action->halt();
                    $this->record->refresh();
                }),

            Actions\Action::make('unsettle')
                ->label('Undo settle')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->visible(fn () => filled($this->record->settle_mode) && \App\Filament\Support\SettleBill::canUndo())
                ->requiresConfirmation()
                ->modalHeading('Undo this settlement?')
                ->modalDescription('The bill will show as Unsettled again. Use this only to fix a wrong mode, then settle it again.')
                ->action(function () { $this->record->unsettle(); $this->record->refresh(); }),

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
                ->label('Accept')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->status === 'pending')
                ->modalHeading('Accept order')
                ->modalDescription('How long will the food take? The kitchen sees a countdown and the customer sees the time.')
                ->modalWidth('md')
                ->fillForm(fn () => ['prep' => (string) ($this->record->prep_time_minutes ?: 20)])
                ->form([
                    Forms\Components\ToggleButtons::make('prep')->label('Ready in (minutes)')
                        ->options(collect(\App\Models\Order::PREP_CHOICES)->mapWithKeys(fn ($m) => [(string) $m => "{$m} min"])->all())
                        ->inline()->required(),
                ])
                ->action(function (array $data) {
                    if ($this->record->status !== 'pending') return;
                    $this->record->advance((int) $data['prep']);
                    \Filament\Notifications\Notification::make()->title("Accepted — ready in {$data['prep']} min")->success()->send();
                }),

            Actions\Action::make('next_step')
                ->label(fn () => $this->record->nextStep()['label'] ?? '')
                ->color(fn () => $this->record->nextStep()['color'] ?? 'gray')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(fn () => $this->record->status !== 'pending' && $this->record->nextStep() !== null)
                ->action(function () {
                    $this->record->advance();
                    $this->record->refresh();
                    \Filament\Notifications\Notification::make()->title($this->record->statusText())->success()->send();
                }),

            Actions\Action::make('reject_order')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => in_array($this->record->status, ['pending', 'accepted'], true))
                ->modalHeading('Reject order')
                ->modalDescription(fn () => $this->record->isPaidViaRazorpay() ? '⚠️ PAID ONLINE — after rejecting, use Refund via Razorpay.' : 'The customer sees the order as cancelled by the restaurant.')
                ->form(fn () => [
                    Forms\Components\Radio::make('reason')->label('Why?')->options(\App\Models\Order::REJECT_REASONS)->required()->live(),
                    Forms\Components\CheckboxList::make('sold_out')
                        ->label('Mark these dishes SOLD OUT')
                        ->options(fn () => $this->record->items->whereNotNull('menu_item_id')->unique('menu_item_id')->mapWithKeys(fn ($i) => [$i->menu_item_id => \App\Filament\Resources\OrderResource::splitItemName((string) $i->name)[0]])->all())
                        ->visible(fn (\Filament\Forms\Get $get) => $get('reason') === 'item_finished'),
                    Forms\Components\TextInput::make('note')->label('Note (optional)')->maxLength(150),
                ])
                ->action(function (array $data) {
                    $this->record->reject($data['reason'], array_map('intval', $data['sold_out'] ?? []), $data['note'] ?? null);
                    $this->record->refresh();
                    \Filament\Notifications\Notification::make()->title('Order rejected')->warning()->send();
                }),

            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
