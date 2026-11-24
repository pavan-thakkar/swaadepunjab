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
    use \App\Filament\Concerns\HasRoleAccess;

    protected static function roleAccess(): array
    {
        return ['view' => ['owner', 'manager', 'staff'], 'edit' => ['owner', 'manager', 'staff'], 'delete' => ['owner']];
    }

    public static function getNavigationBadge(): ?string
    {
        $n = \App\Models\Order::where('status', 'pending')->count();
        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'Orders';
    protected static ?string $navigationGroup = 'Live';
    protected static ?int $navigationSort = 1;

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
                    Forms\Components\Placeholder::make('cancelled_display')
                        ->label('Cancelled')
                        ->visible(fn (?Order $record) => $record?->status === 'cancelled')
                        ->content(fn (Order $record) => new \Illuminate\Support\HtmlString(
                            '<b style="color:#DC2626">' . e($record->cancelledByLabel() ?: 'Cancelled (who is not known — older order)') . '</b>'
                            . ($record->cancel_reason && $record->cancelled_by !== 'system' ? '<div style="font-size:12px;color:#6B5A3E">' . e($record->cancel_reason) . '</div>' : '')
                        )),
                    Forms\Components\Placeholder::make('settlement_display')
                        ->label('Bill settlement')
                        ->content(fn (Order $record) => new \Illuminate\Support\HtmlString(
                            '<b style="color:' . ($record->isSettled() ? '#15803D' : ($record->needsSettlement() ? '#DC2626' : '#6B7280')) . '">' . e($record->settleLabel()) . '</b>'
                            . (($l = \App\Filament\Support\SettleBill::byLine($record)) ? '<div style="font-size:12px;color:#6B5A3E">' . e($l) . '</div>' : '')
                            . ($record->settle_note ? '<div style="font-size:12px;color:#6B5A3E">Note: ' . e($record->settle_note) . '</div>' : '')
                        )),
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
                    Forms\Components\Placeholder::make('customer_edit_notice')
                        ->label('Customer Changes')
                        ->visible(fn (?Order $record) => (bool) $record?->edited_at)
                        ->content(fn (Order $record) => new \Illuminate\Support\HtmlString("<span style='font-weight:800;background:#fef3c7;color:#92400e;padding:6px 12px;border-radius:6px;display:inline-block;'>✏️ Customer changed this order at " . e($record->edited_at?->timezone(config('app.timezone'))->format('h:i A')) . " — check items & note</span>"))
                        ->columnSpanFull(),
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
                            'razorpay'         => match ($record->payment_status) {
                                'refunded' => '↩️ Refunded ₹' . number_format((float) $record->refund_amount, 2) . ($record->refunded_at ? ' · ' . $record->refunded_at->format('d M, h:i A') : '') . ($record->razorpay_refund_id ? ' · ' . $record->razorpay_refund_id : ''),
                                'paid'     => '✅ Paid online (Razorpay)' . ($record->paid_at ? ' · ' . $record->paid_at->format('d M, h:i A') : '') . ($record->razorpay_payment_id ? ' · ' . $record->razorpay_payment_id : '') . ((float) $record->refund_amount > 0 ? ' · ↩️ ₹' . number_format((float) $record->refund_amount, 2) . ' refunded' : ''),
                                'failed'   => '❌ Online payment failed / not completed',
                                default    => '⏳ Waiting for online payment',
                            },
                            'upi_counter'      => '📱 UPI at counter',
                            default            => $record->payment_method,
                        }),
                    Forms\Components\Placeholder::make('status')
                        ->label('Order Status')
                        ->content(fn (Order $record) => $record->statusText() . (($t = $record->timer()) ? ' · ' . $t['text'] : '')),
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
                Forms\Components\TextInput::make('discount')
                    ->label('Offer Discount')
                    ->numeric()
                    ->prefix('− ₹')
                    ->disabled(),
                Forms\Components\TextInput::make('offer_title')
                    ->label('Offer Applied')
                    ->placeholder('No offer')
                    ->disabled(),
                Forms\Components\TextInput::make('coupon_code')
                    ->label('Coupon Used')
                    ->placeholder('No coupon')
                    ->disabled(),
                Forms\Components\TextInput::make('coupon_discount')
                    ->label('Coupon Discount')
                    ->numeric()
                    ->prefix('− ₹')
                    ->disabled(),
                Forms\Components\TextInput::make('points_redeemed')
                    ->label('Loyalty Points Used')
                    ->numeric()
                    ->disabled(),
                Forms\Components\TextInput::make('points_discount')
                    ->label('Points Discount')
                    ->numeric()
                    ->prefix('− ₹')
                    ->disabled(),
                Forms\Components\TextInput::make('gst_amount')
                    ->label(fn (?Order $record) => $record && $record->gst_rate
                        ? 'GST ' . rtrim(rtrim(number_format((float) $record->gst_rate, 2), '0'), '.') . '%' . ($record->gst_mode === 'inclusive' ? ' (included in price)' : ' (added)')
                        : 'GST')
                    ->numeric()
                    ->prefix('₹')
                    ->placeholder('No GST')
                    ->disabled()
                    ->dehydrated(false),
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
                        'upi_counter'      => '📱 UPI at counter',
                    ])
                    ->default('cash_on_delivery'),
            ])->columns(2),

            Forms\Components\Section::make('📋 Order Status')->schema([
                Forms\Components\Select::make('status')
                    ->options([
                        'awaiting_payment' => '💳 Awaiting online payment',
                        'pending'          => '⏳ Pending',
                        'accepted'         => '✅ Accepted',
                        'preparing'        => '✅ Ready / Served',
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
            // Remember filters, search and sorting when the admin leaves and comes back
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->html()
                    ->formatStateUsing(fn (Order $record) => $record->highlighted_order_number_html)
                    ->url(fn (Order $record): string => static::getUrl('view', ['record' => $record]))
                    ->description(fn (Order $record): string => $record->edited_at ? '✏️ Changed by customer at ' . $record->edited_at->timezone(config('app.timezone'))->format('h:i A') : '')
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
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->grow(false),

                Tables\Columns\TextColumn::make('distance_km')
                    ->label('Distance')
                    ->state(fn (Order $record) => $record->distance_km ? "{$record->distance_km} KM" : '—')
                    ->color('info')
                    ->weight('bold')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->grow(false),

                Tables\Columns\ViewColumn::make('ordered_items')
                    ->label('Ordered Items')
                    ->view('filament.tables.columns.order-items'),

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
                    ->formatStateUsing(fn ($state, Order $record) => $record->statusText())
                    ->color(fn ($state) => match ($state) {
                        'pending'          => 'danger',
                        'accepted'         => 'warning',
                        'preparing'        => 'success',
                        'out_for_delivery' => 'info',
                        'delivered'        => 'gray',
                        'cancelled'        => 'danger',
                        default            => 'gray',
                    })
                    ->description(fn (Order $record) => $record->status === 'cancelled'
                        ? $record->cancelledByLabel()
                        : (($t = $record->timer()) ? ($t['state'] === 'late' ? '🔴 ' : ($t['state'] === 'warn' ? '🟠 ' : '')) . ltrim($t['text'], '🔴 ') : null))
                    ->grow(false),

                Tables\Columns\TextColumn::make('order_type')
                    ->label('Type')
                    ->description(fn (Order $record) => $record->order_type === 'delivery'
                        ? ($record->rider_name ? '🛵 ' . $record->rider_name . ($record->picked_up_at ? ' · on the way' : '') : ($record->status === 'cancelled' || $record->status === 'delivered' ? null : '⚠️ No rider yet'))
                        : null)
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
                        'razorpay'         => '💳 Online',
                        'upi_counter'      => '📱 UPI (counter)',
                        default            => $state,
                    })
                    ->description(fn (Order $record) => $record->payment_method === 'razorpay'
                        ? match ($record->payment_status) { 'paid' => '✅ Paid', 'refunded' => '↩️ Refunded', 'failed' => '❌ Not paid', default => '⏳ Waiting' }
                        : null)
                    ->grow(false),

                Tables\Columns\TextColumn::make('settle_mode')
                    ->label('Bill')
                    ->state(fn (Order $record) => $record->settleLabel())
                    ->badge()
                    ->color(fn (Order $record) => $record->isSettled() ? 'success' : ($record->needsSettlement() ? 'danger' : 'gray'))
                    ->description(fn (Order $record) => \App\Filament\Support\SettleBill::byLine($record))
                    ->grow(false),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ordered At')
                    ->dateTime('d M, h:i A')
                    ->sortable()
                    ->grow(false),

                Tables\Columns\TextColumn::make('prep_time_minutes')
                    ->label('Prep Time')
                    ->formatStateUsing(fn ($state) => $state ? "⏱ {$state} min" : '—')
                    ->badge()
                    ->color(fn ($state) => $state ? 'warning' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true)
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

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('print_kot')
                        ->label('KOT (kitchen)')
                        ->icon('heroicon-o-fire')
                        ->url(fn (Order $record) => route('admin.order.print', ['order' => $record->id, 'type' => 'kot']), shouldOpenInNewTab: true),
                    Tables\Actions\Action::make('print_bill_slip')
                        ->label('Bill')
                        ->icon('heroicon-o-document-text')
                        ->url(fn (Order $record) => route('admin.order.print', ['order' => $record->id, 'type' => 'bill']), shouldOpenInNewTab: true),
                    Tables\Actions\Action::make('print_both')
                        ->label('KOT + Bill')
                        ->icon('heroicon-o-document-duplicate')
                        ->url(fn (Order $record) => route('admin.order.print', ['order' => $record->id, 'type' => 'both']), shouldOpenInNewTab: true),
                ])
                    ->label('Print')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->button()
                    ->tooltip('Print KOT for the kitchen or the customer bill'),

                Tables\Actions\Action::make('assign_rider')
                    ->label(fn (?Order $record) => $record?->rider_id ? 'Change rider' : 'Assign rider')
                    ->icon('heroicon-o-user-plus')
                    ->color('info')
                    ->visible(fn (?Order $record) => $record && $record->order_type === 'delivery' && ! in_array($record->status, ['delivered', 'cancelled', 'awaiting_payment'], true))
                    ->modalHeading(fn (Order $record) => "Delivery boy for {$record->order_number}")
                    ->modalDescription('He will see this order on his phone (My Deliveries): customer phone, address, map and amount to collect.')
                    ->modalWidth('md')
                    ->fillForm(fn (Order $record) => ['rider_id' => $record->rider_id])
                    ->form([
                        Forms\Components\Select::make('rider_id')
                            ->label('Delivery boy')
                            ->options(fn () => Order::riderOptions())
                            ->placeholder('— nobody —')
                            ->helperText(fn () => Order::riderOptions() ? null : 'No delivery boys yet. Add one in Staff accounts with the role “Delivery Boy”.'),
                    ])
                    ->action(function (Order $record, array $data) {
                        $record->assignRider($data['rider_id'] ? (int) $data['rider_id'] : null);
                        \Filament\Notifications\Notification::make()->title($record->rider_name ? "Given to {$record->rider_name} 🛵" : 'Delivery boy removed')->success()->send();
                    }),

                Tables\Actions\Action::make('settle')
                    ->label('Settle')
                    ->icon('heroicon-o-banknotes')
                    ->color('danger')
                    ->button()
                    ->visible(fn (?Order $record) => $record && $record->needsSettlement())
                    ->modalHeading('Settle bill')
                    ->modalWidth('lg')
                    ->modalSubmitActionLabel('Settle bill')
                    ->fillForm(fn (Order $record) => \App\Filament\Support\SettleBill::fill($record))
                    ->form(fn (Order $record) => \App\Filament\Support\SettleBill::schema($record))
                    ->action(function (Order $record, array $data, Tables\Actions\Action $action) {
                        if (! \App\Filament\Support\SettleBill::apply($record, $data)) $action->halt();
                    }),

                Tables\Actions\Action::make('unsettle')
                    ->label('Undo settle')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (?Order $record) => $record && filled($record->settle_mode) && \App\Filament\Support\SettleBill::canUndo())
                    ->requiresConfirmation()
                    ->modalHeading('Undo this settlement?')
                    ->modalDescription(fn (Order $record) => "Bill {$record->order_number} will show as Unsettled again and leave the day's Hisab. Use this only to fix a wrong mode, then settle it again.")
                    ->action(fn (Order $record) => $record->unsettle()),

                Tables\Actions\Action::make('accept')
                    ->label('Accept')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->button()
                    ->visible(fn (?Order $record) => $record && $record->status === 'pending')
                    ->modalHeading(fn (Order $record) => "Accept {$record->order_number}")
                    ->modalDescription('How long will the food take? The kitchen sees a countdown and the customer sees the time.')
                    ->modalWidth('md')
                    ->modalSubmitActionLabel('Accept order')
                    ->fillForm(fn (Order $record) => ['prep' => (string) ($record->prep_time_minutes ?: 20)])
                    ->form([
                        Forms\Components\ToggleButtons::make('prep')->label('Ready in (minutes)')
                            ->options(collect(Order::PREP_CHOICES)->mapWithKeys(fn ($m) => [(string) $m => "{$m} min"])->all())
                            ->inline()->required(),
                    ])
                    ->action(function (Order $record, array $data) {
                        if ($record->status !== 'pending') return;
                        $record->advance((int) $data['prep']);
                        \Filament\Notifications\Notification::make()->title("Accepted — ready in {$data['prep']} min")->success()->send();
                    }),

                Tables\Actions\Action::make('next_step')
                    ->label(fn (?Order $record) => $record?->nextStep()['label'] ?? '')
                    ->color(fn (?Order $record) => $record?->nextStep()['color'] ?? 'gray')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->button()
                    ->visible(fn (?Order $record) => $record && $record->status !== 'pending' && $record->nextStep() !== null)
                    ->action(function (Order $record) {
                        if ($record->status === 'preparing' && $record->type() === 'dine_in' && $record->needsSettlement()) {
                            \Filament\Notifications\Notification::make()->title('Completed — bill still unsettled')->body('Remember to settle the bill (Cash / UPI / Card).')->warning()->send();
                        }
                        $to = $record->advance();
                        if ($to) \Filament\Notifications\Notification::make()->title($record->order_number . ': ' . $record->statusText())->success()->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (?Order $record) => $record && in_array($record->status, ['pending', 'accepted'], true))
                    ->modalHeading(fn (Order $record) => ($record->status === 'pending' ? 'Reject ' : 'Cancel ') . $record->order_number)
                    ->modalDescription(fn (Order $record) => $record->isPaidViaRazorpay() ? '⚠️ This order is PAID ONLINE — after rejecting, refund the customer from the order page (Refund via Razorpay).' : 'The customer sees the order as cancelled by the restaurant.')
                    ->modalWidth('lg')
                    ->modalSubmitActionLabel('Reject order')
                    ->form(fn (Order $record) => [
                        Forms\Components\Radio::make('reason')->label('Why?')->options(Order::REJECT_REASONS)->required()->live(),
                        Forms\Components\CheckboxList::make('sold_out')
                            ->label('Mark these dishes SOLD OUT (so no new orders come for them)')
                            ->options(fn () => $record->items->whereNotNull('menu_item_id')->unique('menu_item_id')->mapWithKeys(fn ($i) => [$i->menu_item_id => static::splitItemName((string) $i->name)[0]])->all())
                            ->visible(fn (\Filament\Forms\Get $get) => $get('reason') === 'item_finished')
                            ->columns(1),
                        Forms\Components\TextInput::make('note')->label('Note (optional)')->maxLength(150),
                    ])
                    ->action(function (Order $record, array $data) {
                        $record->reject($data['reason'], array_map('intval', $data['sold_out'] ?? []), $data['note'] ?? null);
                        \Filament\Notifications\Notification::make()->title("{$record->order_number} rejected")
                            ->body(! empty($data['sold_out']) ? count($data['sold_out']) . ' dish(es) marked sold out' : null)->warning()->send();
                    }),

                Tables\Actions\EditAction::make()->label('Edit'),

                Tables\Actions\DeleteAction::make()->label('Delete'),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('settle_many')
                    ->label('Settle selected')
                    ->icon('heroicon-o-banknotes')
                    ->color('danger')
                    ->modalHeading('Settle all selected bills')
                    ->modalDescription('Every selected bill that is still unsettled gets the same payment mode. Settled, cancelled and unpaid online orders are skipped.')
                    ->modalSubmitActionLabel('Settle')
                    ->form([
                        Forms\Components\ToggleButtons::make('mode')->label('Paid by')
                            ->options(['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card'])
                            ->icons(['cash' => 'heroicon-o-banknotes', 'upi' => 'heroicon-o-device-phone-mobile', 'card' => 'heroicon-o-credit-card'])
                            ->colors(['cash' => 'success', 'upi' => 'info', 'card' => 'warning'])
                            ->inline()->required()->default('cash'),
                    ])
                    ->action(function (\Illuminate\Support\Collection $records, array $data) {
                        $n = 0; $sum = 0.0;
                        foreach ($records as $o) {
                            if ($o->needsSettlement()) { $o->settle($data['mode']); $n++; $sum += (float) $o->total; }
                        }
                        \Filament\Notifications\Notification::make()
                            ->title($n ? "{$n} bill" . ($n > 1 ? 's' : '') . ' settled — ₹' . number_format($sum, 2) : 'Nothing to settle')
                            ->body($n < $records->count() ? ($records->count() - $n) . ' skipped (already settled / cancelled / unpaid online)' : null)
                            ->{$n ? 'success' : 'warning'}()->send();
                    })
                    ->deselectRecordsAfterCompletion(),

                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('10s')
            ->recordUrl(fn (Order $record): string => static::getUrl('view', ['record' => $record]));
    }

    /** Split "Paneer Chur Chur Naan Meal - Choose Your Gravy (Dal Makhani)" into the dish and its choices. */
    public static function splitItemName(string $name): array
    {
        $parts = preg_split('/\s+[-–—]\s+/u', $name, 2);
        $dish = trim($parts[0]);
        $extra = '';
        if (isset($parts[1])) {
            preg_match_all('/\(([^)]+)\)/', $parts[1], $m);
            $extra = $m[1] ? implode(', ', $m[1]) : trim($parts[1]);
        }
        return [$dish, $extra];
    }

    /** Ordered items as a short readable list: qty badge, dish in bold, choice below; big orders show the first 4 + "N more". */
    public static function itemsHtml(Order $record, int $show = 4): string
    {
        $items = $record->items;
        $rows = '';
        foreach ($items->take($show) as $it) {
            [$dish, $extra] = static::splitItemName((string) $it->name);
            if (! $extra && is_array($it->options) && $it->options) {
                $extra = collect($it->options)->pluck('choice')->filter()->implode(', ');
            }
            $rows .= '<div style="display:flex;flex-direction:row;width:100%;gap:8px;align-items:flex-start;padding:3px 0;white-space:normal">'
                . '<span style="flex:0 0 auto;min-width:30px;text-align:center;background:#FFF4D6;color:#8A5A00;border-radius:6px;padding:1px 6px;font-weight:900;font-size:13px">' . (int) $it->quantity . '×</span>'
                . '<span style="line-height:1.3"><span style="font-weight:700;font-size:13.5px">' . e($dish) . ($it->is_free ? ' <span style="color:#15803D;font-size:11px;font-weight:800">FREE</span>' : '') . '</span>'
                . ($extra ? '<br><span style="font-size:12px;opacity:.7">' . e($extra) . '</span>' : '')
                . '</span></div>';
        }
        $more = $items->count() - $show;
        $head = '';
        if ($items->count() > $show) {
            $head = '<div style="font-size:11.5px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#B45309;margin-bottom:2px">🧾 Big order · ' . $items->count() . ' dishes · ' . (int) $items->sum('quantity') . ' pcs</div>';
        }
        $tail = $more > 0
            ? '<div style="margin-top:4px"><a href="' . e(static::getUrl('view', ['record' => $record])) . '" style="display:inline-block;font-size:12.5px;font-weight:800;color:#B45309;background:#FFF7E6;border:1px solid #F3D68A;border-radius:999px;padding:2px 10px">+ ' . $more . ' more dish' . ($more > 1 ? 'es' : '') . ' — view all</a></div>'
            : '';
        $note = $record->special_instructions
            ? '<div style="margin-top:4px;font-size:12px;background:#FEF3C7;color:#92400E;border-radius:6px;padding:3px 8px;font-weight:600">📝 ' . e($record->special_instructions) . '</div>'
            : '';
        return '<div style="display:flex;flex-direction:column;width:100%">' . $head . $rows . $tail . $note . '</div>';
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
