<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderFeedbackResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Order ratings from customers (link sent after delivery). Low ratings show as "needs a call". */
class OrderFeedbackResource extends Resource
{
    use \App\Filament\Concerns\HasRoleAccess;

    protected static function roleAccess(): array
    {
        return ['view' => ['owner', 'manager'], 'edit' => ['owner', 'manager'], 'delete' => ['owner']];
    }

    protected static ?string $model = Order::class;
    protected static ?string $slug = 'order-ratings';

    protected static ?string $navigationIcon = 'heroicon-o-star';
    protected static ?string $navigationLabel = 'Order Ratings';
    protected static ?string $modelLabel = 'order rating';
    protected static ?string $pluralModelLabel = 'order ratings';
    protected static ?string $navigationGroup = 'Menu';
    protected static ?int $navigationSort = 6;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNotNull('order_rating');
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Order::badRatingsToCall();
        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Low ratings — call the customer';
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

    public static function stars(?int $n): string
    {
        $n = max(0, min(5, (int) $n));
        return str_repeat('★', $n) . str_repeat('☆', 5 - $n);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('rated_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('rated_at')
                    ->label('Rated')
                    ->dateTime('d M, h:i A')
                    ->description(fn (Order $r) => $r->rated_at?->diffForHumans())
                    ->sortable(),
                Tables\Columns\TextColumn::make('order_rating')
                    ->label('Rating')
                    ->formatStateUsing(fn ($state) => static::stars((int) $state) . "  {$state}/5")
                    ->color(fn ($state) => (int) $state <= 2 ? 'danger' : ((int) $state === 3 ? 'warning' : 'success'))
                    ->weight('bold')
                    ->sortable(),
                Tables\Columns\TextColumn::make('order_feedback')
                    ->label('What they said')
                    ->placeholder('No comment')
                    ->wrap()
                    ->limit(160)
                    ->searchable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Order $r) => '#' . $r->order_number . ' · ₹' . number_format((float) $r->total, 0)),
                Tables\Columns\TextColumn::make('customer_phone')
                    ->label('Mobile')
                    ->searchable()
                    ->copyable()
                    ->url(fn (Order $r) => 'tel:+91' . substr(preg_replace('/\D/', '', (string) $r->customer_phone), -10)),
                Tables\Columns\TextColumn::make('feedback_called_at')
                    ->label('Follow-up')
                    ->state(fn (Order $r) => $r->feedback_called_at ? 'called' : ((int) $r->order_rating <= 2 ? 'call' : 'none'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => ['called' => '✅ Called', 'call' => '📞 Needs a call', 'none' => '—'][$state] ?? $state)
                    ->color(fn ($state) => ['called' => 'success', 'call' => 'danger', 'none' => 'gray'][$state] ?? 'gray')
                    ->description(fn (Order $r) => $r->feedback_called_at
                        ? trim(($r->feedback_called_by ? $r->feedback_called_by . ', ' : '') . $r->feedback_called_at->format('d M h:i A') . ($r->feedback_note ? ' — ' . $r->feedback_note : ''))
                        : null),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('order_rating')
                    ->label('Stars')
                    ->options([5 => '★★★★★ 5', 4 => '★★★★ 4', 3 => '★★★ 3', 2 => '★★ 2', 1 => '★ 1']),
            ])
            ->actions([
                Tables\Actions\Action::make('called')
                    ->label('Mark as called')
                    ->icon('heroicon-o-phone')
                    ->color('success')
                    ->visible(fn (Order $r) => ! $r->feedback_called_at)
                    ->form([
                        Forms\Components\TextInput::make('note')
                            ->label('What did they say? (optional)')
                            ->maxLength(255)
                            ->placeholder('e.g. Food was cold — gave ₹50 coupon'),
                    ])
                    ->action(function (Order $r, array $data) {
                        $r->forceFill([
                            'feedback_called_at' => now(),
                            'feedback_called_by' => auth()->user()?->name,
                            'feedback_note'      => $data['note'] ?? null,
                        ])->save();
                        Notification::make()->title('Marked as called')->success()->send();
                    }),
                Tables\Actions\Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('gray')
                    ->url(fn (Order $r) => 'https://wa.me/91' . substr(preg_replace('/\D/', '', (string) $r->customer_phone), -10)
                        . '?text=' . rawurlencode('Hi ' . trim(explode(' ', (string) $r->customer_name)[0] ?? '') . ', this is Swaad E Punjab. Thank you for your feedback on order #' . $r->order_number . '. '))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('order')
                    ->label('Order')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (Order $r) => OrderResource::getUrl('view', ['record' => $r])),
            ])
            ->emptyStateHeading('No ratings yet')
            ->emptyStateDescription('After an order is delivered, send the customer the rating link (Order → "Send rating link"). Ratings show here.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrderFeedback::route('/'),
        ];
    }
}
