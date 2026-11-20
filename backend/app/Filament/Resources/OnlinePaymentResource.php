<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OnlinePaymentResource\Pages;
use App\Models\Order;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Every online (Razorpay) payment attempt: amount, customer, mobile, success / failed. */
class OnlinePaymentResource extends Resource
{
    use \App\Filament\Concerns\HasRoleAccess;

    protected static function roleAccess(): array
    {
        return ['view' => ['owner', 'manager'], 'edit' => ['owner', 'manager'], 'delete' => ['owner']];
    }

    protected static ?string $model = Order::class;
    protected static ?string $slug = 'online-payments';

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Online Payments';
    protected static ?string $modelLabel = 'online payment';
    protected static ?string $pluralModelLabel = 'online payments';
    protected static ?string $navigationGroup = 'Live';
    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('payment_method', 'razorpay');
    }

    /** success | failed | waiting | refunded */
    public static function state(Order $o): string
    {
        return match (true) {
            $o->payment_status === 'paid'     => 'success',
            $o->payment_status === 'refunded' => 'refunded',
            $o->payment_status === 'failed'   => 'failed',
            $o->status === 'cancelled'        => 'failed', // never paid, order cancelled / timed out
            default                           => 'waiting',
        };
    }

    public static function scopeState(Builder $q, string $state): Builder
    {
        return match ($state) {
            'success'  => $q->where('payment_status', 'paid'),
            'refunded' => $q->where('payment_status', 'refunded'),
            'failed'   => $q->where(fn ($w) => $w->where('payment_status', 'failed')
                ->orWhere(fn ($x) => $x->where('status', 'cancelled')->where(fn ($y) => $y->whereNull('payment_status')->orWhere('payment_status', 'pending')))),
            'waiting'  => $q->where('status', '!=', 'cancelled')->where(fn ($y) => $y->whereNull('payment_status')->orWhere('payment_status', 'pending')),
            default    => $q,
        };
    }

    // Orange number = payments still waiting
    public static function getNavigationBadge(): ?string
    {
        try {
            $n = static::scopeState(Order::where('payment_method', 'razorpay'), 'waiting')->count();
        } catch (\Throwable $e) {
            return null;
        }
        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Online payments still waiting';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date & time')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Order $r) => '#' . $r->order_number),
                Tables\Columns\TextColumn::make('customer_phone')
                    ->label('Mobile')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Number copied')
                    ->url(fn (Order $r) => preg_match('/^\d{10}$/', (string) $r->customer_phone) ? 'tel:+91' . $r->customer_phone : null),
                Tables\Columns\TextColumn::make('total')
                    ->label('Amount')
                    ->money('INR')
                    ->weight('bold')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_state')
                    ->label('Payment')
                    ->badge()
                    ->state(fn (Order $r) => static::state($r))
                    ->formatStateUsing(fn (string $state) => [
                        'success'  => '✅ Success',
                        'failed'   => '❌ Failed',
                        'waiting'  => '⏳ Waiting',
                        'refunded' => '↩️ Refunded',
                    ][$state] ?? $state)
                    ->color(fn (string $state) => [
                        'success' => 'success', 'failed' => 'danger', 'waiting' => 'warning', 'refunded' => 'gray',
                    ][$state] ?? 'gray')
                    ->description(fn (Order $r) => match (static::state($r)) {
                        'success' => ($r->paid_at ? 'Paid ' . $r->paid_at->format('d M, h:i A') : '')
                            . ((float) $r->refund_amount > 0 ? ' · ₹' . number_format((float) $r->refund_amount, 2) . ' refunded' : ''),
                        'refunded' => '₹' . number_format((float) $r->refund_amount, 2) . ' refunded' . ($r->refunded_at ? ' ' . $r->refunded_at->format('d M') : ''),
                        'failed'  => $r->payment_status === 'failed' ? 'Payment declined / failed' : 'Not paid — order cancelled',
                        'waiting' => 'Customer has not finished paying',
                        default   => null,
                    }),
                Tables\Columns\TextColumn::make('razorpay_payment_id')
                    ->label('Razorpay payment ID')
                    ->placeholder('—')
                    ->copyable()
                    ->copyMessage('Payment ID copied')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Order status')
                    ->formatStateUsing(fn (?string $state) => ucwords(str_replace('_', ' ', (string) $state)))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('From'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('Until'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn ($w, $d) => $w->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($w, $d) => $w->whereDate('created_at', '<=', $d)))
                    ->indicateUsing(function (array $data) {
                        $out = [];
                        if ($data['from'] ?? null) $out[] = 'From ' . \Carbon\Carbon::parse($data['from'])->format('d M');
                        if ($data['until'] ?? null) $out[] = 'Until ' . \Carbon\Carbon::parse($data['until'])->format('d M');
                        return $out;
                    }),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->persistFiltersInSession()
            ->actions([
                Tables\Actions\Action::make('check')
                    ->label('Check with Razorpay')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Order $r) => static::state($r) !== 'success' && static::state($r) !== 'refunded' && filled($r->razorpay_order_id))
                    ->action(function (Order $r) {
                        $res = \App\Services\Razorpay::syncFromRazorpayOrder($r);
                        Notification::make()
                            ->title(match ($res) {
                                'paid'    => 'Paid ✅ — money received',
                                'pending' => 'No successful payment on Razorpay yet',
                                'failed'  => 'The customer\'s payment failed',
                                default   => 'Could not reach Razorpay — check the keys in Store & Receipt Config',
                            })
                            ->{$res === 'paid' ? 'success' : ($res === 'error' ? 'danger' : 'warning')}()
                            ->send();
                    }),
                Tables\Actions\Action::make('refund')
                    ->label('Refund')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (Order $r) => \App\Filament\Support\RefundForm::canRefund($r))
                    ->modalHeading('Refund money to the customer')
                    ->modalDescription('Money goes back to the customer\'s UPI / card / bank in 5–7 working days. This cannot be undone.')
                    ->modalSubmitActionLabel('Refund now')
                    ->fillForm(fn (Order $r) => \App\Filament\Support\RefundForm::fill($r))
                    ->form(fn (Order $r) => \App\Filament\Support\RefundForm::schema($r))
                    ->action(fn (Order $r, array $data) => \App\Filament\Support\RefundForm::handle($r, $data)),
                Tables\Actions\Action::make('order')
                    ->label('Order')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (Order $r) => OrderResource::getUrl('view', ['record' => $r])),
            ])
            ->emptyStateHeading('No online payments yet')
            ->emptyStateDescription('Orders paid with Razorpay (UPI / card / net banking) will show here.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOnlinePayments::route('/'),
        ];
    }
}
