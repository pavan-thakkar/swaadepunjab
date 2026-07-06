<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MarketingCardResource\Pages;
use App\Filament\Resources\MarketingCardResource\RelationManagers;
use App\Models\MarketingCard;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MarketingCardResource extends Resource
{
    protected static ?string $model = MarketingCard::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';
    protected static ?string $navigationLabel = 'Promo Banners';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('📢 Promotional Banner Details')->schema([
                    Forms\Components\TextInput::make('tag')
                        ->label('Promo Tag (e.g. 🔥 BEST SELLER)')
                        ->required()
                        ->default('🔥 BEST SELLER'),
                    Forms\Components\TextInput::make('title')
                        ->label('Banner Title')
                        ->required()
                        ->placeholder('e.g. Tandoori Paneer Butter Masala'),
                    Forms\Components\Textarea::make('description')
                        ->label('Banner Description')
                        ->placeholder('Write a mouth-watering description...')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('price')
                        ->label('Discount Price / Text (e.g. ₹260 or 30% OFF)'),
                    Forms\Components\Select::make('menu_item_id')
                        ->label('Link to Menu Item (adds directly to cart)')
                        ->relationship('menuItem', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable(),
                    Forms\Components\TextInput::make('video_url')
                        ->label('Background Video URL (MP4 Link)')
                        ->placeholder('e.g. https://assets.mixkit.co/...mp4')
                        ->url(),
                    Forms\Components\FileUpload::make('image_path')
                        ->label('Fallback Banner Image')
                        ->directory('marketing')
                        ->image()
                        ->imageEditor()
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Visible on Home Page')
                        ->default(true)
                        ->required(),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Display Order')
                        ->required()
                        ->numeric()
                        ->default(0),
                ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable()
                    ->grow(false),
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Image')
                    ->grow(false),
                Tables\Columns\TextColumn::make('tag')
                    ->searchable()
                    ->badge()
                    ->color('warning')
                    ->grow(false),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('price')
                    ->label('Discount/Text')
                    ->searchable()
                    ->grow(false),
                Tables\Columns\TextColumn::make('menuItem.name')
                    ->label('Linked Item')
                    ->sortable()
                    ->placeholder('None'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Visible')
                    ->boolean()
                    ->grow(false),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMarketingCards::route('/'),
            'create' => Pages\CreateMarketingCard::route('/create'),
            'edit' => Pages\EditMarketingCard::route('/{record}/edit'),
        ];
    }
}
