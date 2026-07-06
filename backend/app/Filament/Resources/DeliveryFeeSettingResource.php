<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeliveryFeeSettingResource\Pages;
use App\Models\DeliveryFeeSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DeliveryFeeSettingResource extends Resource
{
    protected static ?string $model = DeliveryFeeSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'Delivery Fee Tiers (KM)';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('🚚 Delivery Tier Config')
                    ->description('Set the delivery fee for orders falling within this road distance range (in KM).')
                    ->schema([
                        Forms\Components\TextInput::make('min_distance_km')
                            ->label('Min Distance')
                            ->numeric()
                            ->suffix('KM')
                            ->default(0)
                            ->required()
                            ->minValue(0),
                        
                        Forms\Components\TextInput::make('max_distance_km')
                            ->label('Max Distance')
                            ->numeric()
                            ->suffix('KM')
                            ->minValue(0)
                            ->placeholder('No Limit (and above)')
                            ->helperText('Leave empty if this is the highest tier (e.g., 15 KM & above).'),

                        Forms\Components\TextInput::make('min_order_amount')
                            ->label('Min Order Value')
                            ->numeric()
                            ->prefix('₹')
                            ->default(0)
                            ->required()
                            ->minValue(0)
                            ->helperText('Minimum order subtotal needed to qualify for this delivery fee.'),

                        Forms\Components\TextInput::make('delivery_fee')
                            ->label('Delivery Fee')
                            ->numeric()
                            ->prefix('₹')
                            ->required()
                            ->minValue(0),
                    ])->columns(4)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('min_distance_km')
                    ->label('Min Distance')
                    ->formatStateUsing(fn ($state) => $state . ' KM')
                    ->sortable(),

                Tables\Columns\TextColumn::make('max_distance_km')
                    ->label('Max Distance')
                    ->formatStateUsing(fn ($state) => $state ? $state . ' KM' : 'And Above (No Limit)')
                    ->color(fn ($state) => $state ? 'default' : 'warning')
                    ->weight(fn ($state) => $state ? 'normal' : 'bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('min_order_amount')
                    ->label('Min Order Value')
                    ->formatStateUsing(fn ($state) => '₹' . number_format((float)$state, 2))
                    ->sortable(),

                Tables\Columns\TextColumn::make('delivery_fee')
                    ->label('Delivery Fee')
                    ->formatStateUsing(fn ($state) => $state == 0 ? 'FREE' : '₹' . number_format((float)$state, 2))
                    ->badge()
                    ->color(fn ($state) => $state == 0 ? 'success' : 'warning')
                    ->sortable(),
            ])
            ->defaultSort('min_distance_km', 'asc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDeliveryFeeSettings::route('/'),
            'create' => Pages\CreateDeliveryFeeSetting::route('/create'),
            'edit'   => Pages\EditDeliveryFeeSetting::route('/{record}/edit'),
        ];
    }
}
