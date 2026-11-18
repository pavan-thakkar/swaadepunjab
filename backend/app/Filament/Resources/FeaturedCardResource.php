<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FeaturedCardResource\Pages;
use App\Models\FeaturedCard;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FeaturedCardResource extends Resource
{
    protected static ?string $model = FeaturedCard::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';
    protected static ?string $navigationLabel = "What's New Cards";
    protected static ?string $modelLabel = 'card';
    protected static ?string $pluralModelLabel = "What's New Cards";
    protected static ?string $navigationGroup = 'Catalog';
    protected static ?int $navigationSort = 0;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Card')->schema([
                Forms\Components\FileUpload::make('image_path')
                    ->label('Card Image')
                    ->helperText('Wide banner image, best size 1080 × 600 px (about 16:9). Put your text and price on the image itself.')
                    ->image()
                    ->imageEditor()
                    ->imageEditorAspectRatios(['16:9', '9:5', null])
                    ->disk('public')
                    ->directory('featured-cards')
                    ->visibility('public')
                    ->maxSize(5120)
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('title')
                    ->label('Card Name')
                    ->helperText('For your reference and for screen readers. Not shown on the card.')
                    ->maxLength(120),
                Forms\Components\Select::make('badge')
                    ->label('Badge on Card (optional)')
                    ->options([
                        'NEW'          => '🆕 NEW',
                        'BEST SELLER'  => '🔥 BEST SELLER',
                        'OFFER'        => '🏷️ OFFER',
                        "CHEF'S SPECIAL" => "👨‍🍳 CHEF'S SPECIAL",
                    ])
                    ->placeholder('No badge'),
                Forms\Components\Select::make('menu_item_id')
                    ->label('Open this dish when tapped (optional)')
                    ->relationship('menuItem', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Display Order')
                    ->helperText('Lower number shows first.')
                    ->numeric()
                    ->default(0),
                Forms\Components\Toggle::make('is_active')
                    ->label('Show on website')
                    ->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')->label('Image')->disk('public')->height(60)->width(108),
                Tables\Columns\TextColumn::make('title')->label('Card Name')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('badge')->badge()->placeholder('—'),
                Tables\Columns\TextColumn::make('menuItem.name')->label('Opens Dish')->placeholder('—'),
                Tables\Columns\ToggleColumn::make('is_active')->label('Visible'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListFeaturedCards::route('/'),
            'create' => Pages\CreateFeaturedCard::route('/create'),
            'edit'   => Pages\EditFeaturedCard::route('/{record}/edit'),
        ];
    }
}
