<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'Orders';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('items');
    }

    public static function form(Form $form): Form
    {
        if ($form->getOperation() === 'view') {
            return $form->schema([
                Forms\Components\Section::make('📋 Order Details & Customer')->schema([
                    Forms\Components\Placeholder::make('customer')
                        ->label('Customer')
                        ->content(fn (Order $record) => "👤 {$record->customer_name} ({$record->customer_phone})"),
                    Forms\Components\Placeholder::make('order_type_display')
                        ->label('Order Type')
                        ->content(fn (Order $record) => match ($record->order_type) {
                            'dine_in'  => new \Illuminate\Support\HtmlString("<span style='font-size: 1.15rem; font-weight: 800; background: #22c55e; color: white; padding: 6px 12px; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 6px rgba(34,197,94,0.2);'>🍽️ Dine In (Arriving at: {$record->pickup_time})</span>"),
                            'takeaway' => new \Illuminate\Support\HtmlString("<span style='font-size: 1.15rem; font-weight: 800; background: #eab308; color: white; padding: 6px 12px; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 6px rgba(234,179,8,0.2);'>🛍️ Take Away (Pickup at: {$record->pickup_time})</span>"),
                            'delivery' => new \Illuminate\Support\HtmlString("<span style='font-size: 1.15rem; font-weight: 800; background: #3b82f6; color: white; padding: 6px 12px; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 6px rgba(59,130,246,0.2);'>🛵 Delivery</span>"),
                            default    => $record->order_type,
                        }),
                    Forms\Components\Placeholder::make('address')
                        ->label('Delivery/Dine/Takeaway Details')
                        ->content(fn (Order $record) => "📍 {$record->delivery_address}, {$record->city}"),
                    Forms\Components\Placeholder::make('special_instructions')
                        ->label('Special Instructions')
                        ->content(fn (Order $record) => $record->special_instructions ?: 'None')
                        ->columnSpanFull(),
                ])->columns(2),

                Forms\Components\Section::make('🍲 Ordered Items')->schema([
                    Forms\Components\Repeater::make('items')
                        ->relationship('items')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Item Name'),
                            Forms\Components\TextInput::make('quantity')
                                ->label('Qty'),
                            Forms\Components\TextInput::make('price')
                                ->label('Price')
                                ->prefix('₹'),
                            Forms\Components\TextInput::make('subtotal')
                                ->label('Subtotal')
                                ->prefix('₹'),
                        ])
                        ->columns(4)
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->columnSpanFull()
                ]),

                Forms\Components\Section::make('💰 Financials & Status')->schema([
                    Forms\Components\Placeholder::make('payment_method')
                        ->label('Payment Method')
                        ->content(fn (Order $record) => match ($record->payment_method) {
                            'cash_on_delivery' => '💵 Cash on Delivery',
                            'card'             => '💳 Card',
                            'razorpay'         => '💳 Razorpay (Online)',
                            default            => $record->payment_method,
                        }),
                    Forms\Components\Placeholder::make('status')
                        ->label('Order Status')
                        ->content(fn (Order $record) => match ($record->status) {
                            'pending'          => '⏳ Pending',
                            'accepted'         => '✅ Accepted',
                            'preparing'        => '🍳 Ready',
                            'out_for_delivery' => '🛵 Out for Delivery',
                            'delivered'        => '🎉 Delivered',
                            'cancelled'        => '❌ Cancelled',
                            default            => $record->status,
                        }),
                    Forms\Components\Placeholder::make('financial_totals')
                        ->label('Order Totals')
                        ->content(fn (Order $record) => new \Illuminate\Support\HtmlString("
                            Subtotal: <strong>₹" . number_format($record->subtotal, 0) . "</strong><br>
                            Delivery Fee: <strong>₹" . number_format($record->delivery_fee, 0) . "</strong><br>
                            Total Amount: <strong style='font-size: 1.1rem; color: #16a34a;'>₹" . number_format($record->total, 0) . "</strong>
                        ")),
                    Forms\Components\Placeholder::make('prep_time_minutes')
                        ->label('Preparation Details')
                        ->content(fn (Order $record) => $record->prep_time_minutes ? "⏱ {$record->prep_time_minutes} minutes" : 'Not accepted yet'),
                ])->columns(2),
            ]);
        }

        return $form->schema([
            Forms\Components\Section::make('👤 Customer Info')->schema([
                Forms\Components\TextInput::make('customer_name')
                    ->label('Full Name')
                    ->required(),
                Forms\Components\TextInput::make('customer_email')
                    ->label('Email')
                    ->email(),
                Forms\Components\TextInput::make('customer_phone')
                    ->label('Phone')
                    ->required(),
                Forms\Components\Select::make('order_type')
                    ->label('Order Type')
                    ->options([
                        'delivery' => '🛵 Delivery',
                        'dine_in'  => '🍽️ Dine In',
                        'takeaway' => '🛍️ Take Away',
                    ])
                    ->required()
                    ->default('delivery'),
                Forms\Components\TextInput::make('table_number')
                    ->label('Table Number (Dine In)'),
                Forms\Components\TextInput::make('pickup_time')
                    ->label('Pickup Time (Take Away)'),
                Forms\Components\TextInput::make('city')
                    ->label('City')
                    ->required(),
                Forms\Components\Textarea::make('delivery_address')
                    ->label('Address / Location Info')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Placeholder::make('coordinates_map')
                    ->label('Google Maps Location')
                    ->content(fn (?Order $record) => ($record && $record->latitude) 
                        ? new \Illuminate\Support\HtmlString("<a href='https://www.google.com/maps/search/?api=1&query={$record->latitude},{$record->longitude}' target='_blank' style='color: #d97706; font-weight: bold; text-decoration: underline; display: inline-flex; align-items: center; gap: 4px;'>📍 Open Delivery Location in Google Maps</a>")
                        : 'No GPS coordinates saved for this order.')
                    ->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('🍲 Ordered Items')->schema([
                Forms\Components\Repeater::make('items')
                    ->relationship('items')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Item Name')
                            ->disabled(),
                        Forms\Components\TextInput::make('quantity')
                            ->label('Qty')
                            ->numeric()
                            ->disabled(),
                        Forms\Components\TextInput::make('price')
                            ->label('Price')
                            ->numeric()
                            ->prefix('₹')
                            ->disabled(),
                        Forms\Components\TextInput::make('subtotal')
                            ->label('Subtotal')
                            ->numeric()
                            ->prefix('₹')
                            ->disabled(),
                    ])
                    ->columns(4)
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->columnSpanFull()
            ]),

            Forms\Components\Section::make('💰 Financials')->schema([
                Forms\Components\TextInput::make('subtotal')
                    ->numeric()
                    ->prefix('₹')
                    ->disabled(),
                Forms\Components\TextInput::make('delivery_fee')
                    ->label('Delivery Fee')
                    ->numeric()
                    ->prefix('₹')
                    ->disabled(),
                Forms\Components\TextInput::make('distance_km')
                    ->label('Calculated Distance')
                    ->numeric()
                    ->suffix('KM')
                    ->disabled(),
                Forms\Components\TextInput::make('total')
                    ->numeric()
                    ->prefix('₹')
                    ->disabled(),
                Forms\Components\Select::make('payment_method')
                    ->label('Payment Method')
                    ->options([
                        'cash_on_delivery' => '💵 Cash on Delivery',
                        'card'             => '💳 Card',
                        'razorpay'         => '💳 Razorpay (Online)',
                    ])
                    ->default('cash_on_delivery'),
            ])->columns(2),

            Forms\Components\Section::make('📋 Order Status')->schema([
                Forms\Components\Select::make('status')
                    ->options([
                        'pending'          => '⏳ Pending',
                        'accepted'         => '✅ Accepted',
                        'preparing'        => '🍳 Ready',
                        'out_for_delivery' => '🛵 Out for Delivery',
                        'delivered'        => '🎉 Delivered',
                        'cancelled'        => '❌ Cancelled',
                    ])
                    ->required()
                    ->native(false),
                Forms\Components\TextInput::make('prep_time_minutes')
                    ->label('Prep Time (minutes)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(120)
                    ->suffix('min')
                    ->placeholder('e.g. 20')
                    ->helperText('Set when accepting the order. Shown to customer.'),
                Forms\Components\DateTimePicker::make('estimated_delivery_at')
                    ->label('Estimated Delivery At')
                    ->displayFormat('d M Y, h:i A')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('special_instructions')
                    ->label('Special Instructions')
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->html()
                    ->formatStateUsing(fn (Order $record) => $record->highlighted_order_number_html)
                    ->url(fn (Order $record): string => static::getUrl('view', ['record' => $record]))
                    ->grow(false),

                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->grow(false),

                Tables\Columns\TextColumn::make('latitude')
                    ->label('Location')
                    ->state(fn (Order $record) => $record->latitude ? '📍 View Map' : 'No GPS')
                    ->url(fn (Order $record) => $record->latitude ? "https://www.google.com/maps/search/?api=1&query={$record->latitude},{$record->longitude}" : null, shouldOpenInNewTab: true)
                    ->color(fn (Order $record) => $record->latitude ? 'success' : 'gray')
                    ->weight('bold')
                    ->grow(false),

                Tables\Columns\TextColumn::make('distance_km')
                    ->label('Distance')
                    ->state(fn (Order $record) => $record->distance_km ? "{$record->distance_km} KM" : '—')
                    ->color('info')
                    ->weight('bold')
                    ->grow(false),

                Tables\Columns\TextColumn::make('ordered_items')
                    ->label('Ordered Items')
                    ->state(function (Order $record): string {
                        return $record->items->map(fn ($item) => "{$item->quantity}x {$item->name}")->implode(', ');
                    })
                    ->wrap()
                    ->description(fn (Order $record): string => $record->special_instructions ? "Note: {$record->special_instructions}" : ''),

                Tables\Columns\TextColumn::make('customer_phone')
                    ->label('Phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('city')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => $state ? '₹' . number_format((float)$state, 2) : '—')
                    ->sortable()
                    ->grow(false),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pending'          => '⏳ Pending',
                        'accepted'         => '✅ Accepted',
                        'preparing'        => '🍳 Ready',
                        'out_for_delivery' => '🛵 Out for Delivery',
                        'delivered'        => '🎉 Delivered',
                        'cancelled'        => '❌ Cancelled',
                        default            => $state ?? 'Unknown',
                    })
                    ->color(fn ($state) => match ($state) {
                        'pending'          => 'warning',
                        'accepted'         => 'info',
                        'preparing'        => 'primary',
                        'out_for_delivery' => 'primary',
                        'delivered'        => 'success',
                        'cancelled'        => 'danger',
                        default            => 'gray',
                    })
                    ->grow(false),

                Tables\Columns\TextColumn::make('order_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'dine_in'   => '🍽️ Dine In',
                        'takeaway'  => '🛍️ Take Away',
                        'delivery'  => '🛵 Delivery',
                        default     => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'dine_in'   => 'success',
                        'takeaway'  => 'warning',
                        'delivery'  => 'info',
                        default     => 'gray',
                    })
                    ->grow(false),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Payment')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'cash_on_delivery' => '💵 COD',
                        'card'             => '💳 Card',
                        'razorpay'         => '💳 Razorpay',
                        default            => $state,
                    })
                    ->grow(false),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ordered At')
                    ->dateTime('d M Y, h:i A')
                    ->sortable()
                    ->grow(false),

                Tables\Columns\TextColumn::make('prep_time_minutes')
                    ->label('Prep Time')
                    ->formatStateUsing(fn ($state) => $state ? "⏱ {$state} min" : '—')
                    ->badge()
                    ->color(fn ($state) => $state ? 'warning' : 'gray')
                    ->grow(false),
            ])
            ->filters([
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['created_from'] ?? null) {
                            $indicators['created_from'] = 'Order from ' . \Carbon\Carbon::parse($data['created_from'])->format('M d, Y');
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators['created_until'] = 'Order until ' . \Carbon\Carbon::parse($data['created_until'])->format('M d, Y');
                        }
                        return $indicators;
                    })
            ])
            ->actions([
                Tables\Actions\Action::make('print_bill')
                    ->label('Bill')
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->modalContent(fn ($record) => view('admin.order-bill', ['order' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Tables\Actions\Action::make('accept')
                    ->label('Accept')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record && $record->status === 'pending')
                    ->action(fn ($record) => $record->update(['status' => 'accepted'])),

                Tables\Actions\Action::make('prepare')
                    ->label('Ready')
                    ->icon('heroicon-o-check-circle')
                    ->color('warning')
                    ->visible(fn ($record) => $record && $record->status === 'accepted')
                    ->action(fn ($record) => $record->update(['status' => 'preparing'])),

                Tables\Actions\Action::make('dispatch')
                    ->label('Dispatch')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->visible(fn ($record) => $record && $record->status === 'preparing')
                    ->action(fn ($record) => $record->update(['status' => 'out_for_delivery'])),

                Tables\Actions\Action::make('deliver')
                    ->label('Delivered')
                    ->icon('heroicon-o-gift')
                    ->color('success')
                    ->visible(fn ($record) => $record && $record->status === 'out_for_delivery')
                    ->action(fn ($record) => $record->update(['status' => 'delivered'])),

                Tables\Actions\EditAction::make()->label('Edit'),

                Tables\Actions\DeleteAction::make()->label('Delete'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('10s')
            ->recordUrl(fn (Order $record): string => static::getUrl('view', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view'   => Pages\ViewOrder::route('/{record}'),
            'edit'   => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
