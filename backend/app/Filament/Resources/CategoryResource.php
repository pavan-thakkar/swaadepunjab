<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CategoryResource extends Resource
{
    use \App\Filament\Concerns\HasRoleAccess;

    protected static function roleAccess(): array
    {
        return ['view' => ['owner', 'manager'], 'edit' => ['owner', 'manager'], 'delete' => ['owner', 'manager']];
    }

    protected static ?string $model = Category::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'Categories';
    protected static ?string $navigationGroup = 'Menu';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Category Details')->schema([

                Forms\Components\FileUpload::make('image')
                    ->label('Category image / icon (optional)')
                    ->helperText('Square photo or icon works best (PNG with transparent background, JPG or WebP, up to 2 MB). Shown in the round category circle on the website instead of the emoji.')
                    ->image()
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                    ->imageEditor()
                    ->imageEditorAspectRatios(['1:1'])
                    ->imageCropAspectRatio('1:1')
                    ->imageResizeTargetWidth('400')
                    ->imageResizeTargetHeight('400')
                    ->disk('public')
                    ->directory('categories')
                    ->visibility('public')
                    ->maxSize(2048)
                    ->openable()
                    ->live()
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('emoji')
                    ->label('Emoji Icon')
                    ->placeholder('🧀')
                    ->helperText('Used when there is no image.')
                    ->maxLength(10)
                    ->default('🍽️')
                    ->required(fn (Forms\Get $get) => empty($get('image')))
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? $state : '🍽️'), // never save empty into a required column

                Forms\Components\TextInput::make('name')
                    ->label('Category Name')
                    ->required()
                    ->maxLength(100)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                        $set('slug', Str::snake(strtolower($state ?? '')));
                    }),

                Forms\Components\TextInput::make('slug')
                    ->label('Slug (used in API & frontend)')
                    ->required()
                    ->unique(Category::class, 'slug', ignoreRecord: true)
                    ->helperText('Auto-generated. E.g. paneer_sabji')
                    ->maxLength(100),

                Forms\Components\Textarea::make('description')
                    ->label('Description (optional)')
                    ->rows(2)
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('sort_order')
                    ->label('Sort Order')
                    ->numeric()
                    ->default(0)
                    ->helperText('Lower number = shown first')
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? $state : 0), // never save empty into a required column

                Forms\Components\Toggle::make('is_active')
                    ->label('Active (visible on frontend)')
                    ->default(true),

                Forms\Components\Toggle::make('is_addon')
                    ->label('Sides / extras category')
                    ->helperText('Breads, papad, drinks, desserts… Suggested as "Goes well with" on main dishes.')
                    ->default(false),

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
                Tables\Columns\ImageColumn::make('image')
                    ->label('')
                    ->disk('public')
                    ->circular()
                    ->size(36)
                    ->defaultImageUrl(null),

                Tables\Columns\TextColumn::make('emoji')
                    ->label('')
                    ->width(40),

                Tables\Columns\TextColumn::make('name')
                    ->label('Category Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->badge()
                    ->color('gray')
                    ->copyable(),

                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),

                Tables\Columns\ToggleColumn::make('is_addon')
                    ->label('Sides'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Category')
                    ->modalDescription('Are you sure you want to delete this category? This cannot be undone.')
                    ->modalSubmitActionLabel('Yes, Delete'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit'   => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
