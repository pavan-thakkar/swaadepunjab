<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OfferResource\Pages;
use App\Models\Offer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OfferResource extends Resource
{
    use \App\Filament\Concerns\HasRoleAccess;

    protected static function roleAccess(): array
    {
        return ['view' => ['owner', 'manager'], 'edit' => ['owner', 'manager'], 'delete' => ['owner', 'manager']];
    }

    protected static ?string $model = Offer::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';
    protected static ?string $navigationLabel = 'Offers & Free Items';
    protected static ?string $modelLabel = 'offer';
    protected static ?string $navigationGroup = 'Grow';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Offer')->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Offer Name (shown to customers)')
                    ->placeholder('e.g. Free Paneer Tikka on ₹1000+')
                    ->required()
                    ->maxLength(80),
                Forms\Components\TextInput::make('min_order_amount')
                    ->label('Minimum Order Amount')
                    ->helperText('Items total (before delivery fee) needed to unlock this offer.')
                    ->numeric()
                    ->minValue(1)
                    ->prefix('₹')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->label('Short Description (optional)')
                    ->rows(2)
                    ->maxLength(255)
                    ->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('Reward')->schema([
                Forms\Components\Radio::make('reward_type')
                    ->label('What does the customer get?')
                    ->options([
                        'free_item'        => '🎁 A free item',
                        'flat_discount'    => '💸 Flat ₹ discount',
                        'percent_discount' => '% Percentage discount',
                    ])
                    ->default('free_item')
                    ->live()
                    ->required()
                    ->columnSpanFull(),

                Forms\Components\Select::make('free_menu_item_id')
                    ->label('Free Item')
                    ->relationship('freeItem', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get) => $get('reward_type') === 'free_item')
                    ->required(fn (Get $get) => $get('reward_type') === 'free_item'),
                Forms\Components\TextInput::make('free_quantity')
                    ->label('Free Quantity')
                    ->numeric()->integer()->minValue(1)->maxValue(10)
                    ->default(1)
                    ->visible(fn (Get $get) => $get('reward_type') === 'free_item')
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? $state : 1), // never save empty into a required column

                Forms\Components\TextInput::make('discount_value')
                    ->label(fn (Get $get) => $get('reward_type') === 'percent_discount' ? 'Discount %' : 'Discount Amount')
                    ->prefix(fn (Get $get) => $get('reward_type') === 'percent_discount' ? null : '₹')
                    ->suffix(fn (Get $get) => $get('reward_type') === 'percent_discount' ? '%' : null)
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(fn (Get $get) => $get('reward_type') === 'percent_discount' ? 100 : null)
                    ->visible(fn (Get $get) => in_array($get('reward_type'), ['flat_discount', 'percent_discount']))
                    ->required(fn (Get $get) => in_array($get('reward_type'), ['flat_discount', 'percent_discount'])),
                Forms\Components\TextInput::make('max_discount')
                    ->label('Maximum Discount (optional)')
                    ->helperText('e.g. 20% off, up to ₹200')
                    ->numeric()
                    ->prefix('₹')
                    ->visible(fn (Get $get) => $get('reward_type') === 'percent_discount'),
            ])->columns(2),

            Forms\Components\Section::make('When')->schema([
                Forms\Components\Toggle::make('is_active')->label('Offer is ON')->default(true)->columnSpanFull(),
                Forms\Components\DateTimePicker::make('starts_at')->label('Starts (optional)')->seconds(false),
                Forms\Components\DateTimePicker::make('ends_at')->label('Ends (optional)')->seconds(false)->after('starts_at'),
            ])->columns(2)
              ->description('Only one offer applies per order: the biggest one the order qualifies for.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Remember filters, search and sorting when the admin leaves and comes back
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->defaultSort('min_order_amount')
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('Offer')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('min_order_amount')->label('Min. Order')->money('INR')->sortable(),
                Tables\Columns\TextColumn::make('reward')
                    ->label('Reward')
                    ->state(fn (Offer $r) => match ($r->reward_type) {
                        'free_item'        => '🎁 ' . ($r->free_quantity > 1 ? $r->free_quantity . '× ' : '') . ($r->freeItem?->name ?? '—') . ' FREE',
                        'flat_discount'    => '💸 ₹' . number_format((float) $r->discount_value, 0) . ' OFF',
                        'percent_discount' => number_format((float) $r->discount_value, 0) . '% OFF' . ($r->max_discount ? ' (max ₹' . number_format((float) $r->max_discount, 0) . ')' : ''),
                        default            => '—',
                    }),
                Tables\Columns\TextColumn::make('ends_at')->label('Ends')->dateTime('d M, h:i A')->placeholder('No end'),
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
            'index'  => Pages\ListOffers::route('/'),
            'create' => Pages\CreateOffer::route('/create'),
            'edit'   => Pages\EditOffer::route('/{record}/edit'),
        ];
    }
}
