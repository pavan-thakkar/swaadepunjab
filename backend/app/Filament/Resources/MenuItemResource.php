<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuItemResource\Pages;
use App\Models\MenuItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MenuItemResource extends Resource
{
    protected static ?string $model = MenuItem::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Menu Items';
    protected static ?string $navigationGroup = 'Catalog';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Item Details')->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('category')
                    ->required()
                    ->native(false)
                    ->options(fn () => \App\Models\Category::all()->mapWithKeys(function ($cat) {
                        return [$cat->slug => ($cat->emoji ? $cat->emoji . ' ' : '') . $cat->name];
                    })),
                Forms\Components\Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('image')
                    ->image()
                    ->directory('menu-items')
                    ->multiple()
                    ->reorderable()
                    ->imageEditor()
                    ->maxSize(2048)
                    ->columnSpanFull()
                    ->label('Food Images'),
            ])->columns(2),

            Forms\Components\Section::make('Pricing & Time')->schema([
                Forms\Components\TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('₹')
                    ->minValue(0),
                Forms\Components\TextInput::make('prep_time')
                    ->numeric()
                    ->suffix('min')
                    ->default(20),
                Forms\Components\TextInput::make('rating')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(5)
                    ->step(0.1)
                    ->default(4.5),
            ])->columns(3),

            Forms\Components\Section::make('Visibility')->schema([
                Forms\Components\Toggle::make('is_available')
                    ->label('Available for ordering')
                    ->default(true),
                Forms\Components\Toggle::make('is_featured')
                    ->label('Featured on homepage'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Photo')
                    ->circular()
                    ->stacked(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(function (string $state): string {
                        $cat = \App\Models\Category::where('slug', $state)->first();
                        if ($cat) {
                            return ($cat->emoji ? $cat->emoji . ' ' : '') . $cat->name;
                        }
                        return $state;
                    })
                    ->color('gray'),

                Tables\Columns\TextColumn::make('price')
                    ->formatStateUsing(fn ($state) => '₹' . number_format($state, 0))
                    ->sortable(),

                Tables\Columns\TextColumn::make('prep_time')
                    ->suffix(' min')
                    ->label('Prep Time')
                    ->sortable(),

                Tables\Columns\TextColumn::make('rating')
                    ->formatStateUsing(fn ($state) => '★ ' . $state)
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_available')
                    ->boolean()
                    ->label('Available'),

                Tables\Columns\IconColumn::make('is_featured')
                    ->boolean()
                    ->label('Featured'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options(fn () => \App\Models\Category::all()->mapWithKeys(function ($cat) {
                        return [$cat->slug => ($cat->emoji ? $cat->emoji . ' ' : '') . $cat->name];
                    })),
                Tables\Filters\TernaryFilter::make('is_available')->label('Available'),
                Tables\Filters\TernaryFilter::make('is_featured')->label('Featured'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('category');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMenuItems::route('/'),
            'create' => Pages\CreateMenuItem::route('/create'),
            'edit'   => Pages\EditMenuItem::route('/{record}/edit'),
            'import' => Pages\ImportMenuItems::route('/import'),
        ];
    }
}
