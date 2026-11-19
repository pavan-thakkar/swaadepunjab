<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CouponResource\Pages;
use App\Models\Coupon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CouponResource extends Resource
{
    use \App\Filament\Concerns\HasRoleAccess;

    protected static function roleAccess(): array
    {
        return ['view' => ['owner', 'manager'], 'edit' => ['owner', 'manager'], 'delete' => ['owner', 'manager']];
    }

    protected static ?string $model = Coupon::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationLabel = 'Coupon Codes';
    protected static ?string $modelLabel = 'coupon';
    protected static ?string $navigationGroup = 'Grow';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Coupon')->schema([
                Forms\Components\TextInput::make('code')
                    ->label('Coupon Code')
                    ->placeholder('e.g. SWAAD50')
                    ->helperText('Customers type this at checkout. Letters and numbers only.')
                    ->required()
                    ->maxLength(40)
                    ->regex('/^[A-Za-z0-9_-]+$/')
                    ->unique(ignoreRecord: true)
                    ->extraInputAttributes(['style' => 'text-transform: uppercase']),
                Forms\Components\TextInput::make('description')
                    ->label('Short Description (optional)')
                    ->placeholder('e.g. ₹50 off on your first order')
                    ->maxLength(200),
                Forms\Components\Radio::make('discount_type')
                    ->label('Discount Type')
                    ->options(['flat' => '💸 Flat ₹ off', 'percent' => '% off'])
                    ->default('flat')
                    ->inline()
                    ->live()
                    ->required(),
                Forms\Components\TextInput::make('discount_value')
                    ->label(fn (Get $get) => $get('discount_type') === 'percent' ? 'Discount %' : 'Discount Amount')
                    ->prefix(fn (Get $get) => $get('discount_type') === 'percent' ? null : '₹')
                    ->suffix(fn (Get $get) => $get('discount_type') === 'percent' ? '%' : null)
                    ->numeric()->minValue(1)
                    ->maxValue(fn (Get $get) => $get('discount_type') === 'percent' ? 100 : null)
                    ->required(),
                Forms\Components\TextInput::make('max_discount')
                    ->label('Maximum Discount (optional)')
                    ->helperText('e.g. 20% off, up to ₹150')
                    ->numeric()->prefix('₹')
                    ->visible(fn (Get $get) => $get('discount_type') === 'percent'),
                Forms\Components\TextInput::make('min_order_amount')
                    ->label('Minimum Order Amount')
                    ->numeric()->prefix('₹')->default(0)
                    ->helperText('Items total needed to use this coupon. 0 = no minimum.')
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? $state : 0), // never save empty into a required column
            ])->columns(2),

            Forms\Components\Section::make('Limits')->schema([
                Forms\Components\TextInput::make('usage_limit')
                    ->label('Total uses allowed (optional)')
                    ->helperText('Leave empty for unlimited.')
                    ->numeric()->integer()->minValue(1),
                Forms\Components\TextInput::make('per_customer_limit')
                    ->label('Uses per customer (phone number)')
                    ->helperText('Leave empty for unlimited.')
                    ->numeric()->integer()->minValue(1)->default(1),
                Forms\Components\Toggle::make('combine_with_offers')
                    ->label('Can be used together with automatic offers')
                    ->helperText('If OFF, using this coupon replaces any free item / offer discount.')
                    ->default(false)
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('starts_at')->label('Starts (optional)')->seconds(false),
                Forms\Components\DateTimePicker::make('ends_at')->label('Expires (optional)')->seconds(false)->after('starts_at'),
                Forms\Components\Toggle::make('is_active')->label('Coupon is ON')->default(true),
                Forms\Components\Placeholder::make('used_count_display')
                    ->label('Used so far')
                    ->content(fn (?Coupon $record) => ($record?->used_count ?? 0) . ' times')
                    ->visibleOn('edit'),
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
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Code')->searchable()->weight('bold')->copyable(),
                Tables\Columns\TextColumn::make('discount')
                    ->label('Discount')
                    ->state(fn (Coupon $c) => $c->discount_type === 'percent'
                        ? number_format((float) $c->discount_value, 0) . '% OFF' . ($c->max_discount ? ' (max ₹' . number_format((float) $c->max_discount, 0) . ')' : '')
                        : '₹' . number_format((float) $c->discount_value, 0) . ' OFF'),
                Tables\Columns\TextColumn::make('min_order_amount')->label('Min. Order')->money('INR'),
                Tables\Columns\TextColumn::make('used_count')
                    ->label('Used')
                    ->formatStateUsing(fn ($state, Coupon $c) => $state . ($c->usage_limit ? ' / ' . $c->usage_limit : '')),
                Tables\Columns\TextColumn::make('ends_at')->label('Expires')->dateTime('d M, h:i A')->placeholder('Never'),
                Tables\Columns\ToggleColumn::make('is_active')->label('ON'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit'   => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}
