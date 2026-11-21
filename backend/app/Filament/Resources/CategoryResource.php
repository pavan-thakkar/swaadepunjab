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
                    ->label('Photo')
                    ->disk('public')
                    ->circular()
                    ->size(44)
                    // No saved image yet → show the dish photo the website is using automatically
                    ->defaultImageUrl(function (Category $record) {
                        $path = array_key_first($record->dishPhotos(1));
                        return $path ? (str_starts_with($path, 'http') ? $path : asset('storage/' . $path)) : asset('images/brand/logo.png');
                    })
                    ->tooltip(fn (Category $record) => filled($record->image)
                        ? 'Saved category photo'
                        : ($record->dishPhotos(1) ? 'Automatic (from a dish photo) — click "Fill missing images" or "Choose dish photo" to save it' : 'No photo — add dish photos or upload one')),

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
                Tables\Actions\Action::make('dishPhoto')
                    ->label('Choose dish photo')
                    ->icon('heroicon-o-photo')
                    ->color('gray')
                    ->modalHeading(fn (Category $record) => 'Photo for ' . $record->name)
                    ->modalDescription('Pick one of this category\'s dish photos. It is copied, so it stays even if the dish photo changes later.')
                    ->form(fn (Category $record) => [
                        Forms\Components\Radio::make('photo')
                            ->hiddenLabel()
                            ->required()
                            ->options(collect($record->dishPhotos())->mapWithKeys(fn ($name, $path) => [
                                $path => new \Illuminate\Support\HtmlString(
                                    '<span style="display:inline-flex;align-items:center;gap:12px">'
                                    . '<img src="' . e(asset('storage/' . $path)) . '" alt="" style="width:64px;height:64px;border-radius:50%;object-fit:cover;box-shadow:0 4px 10px rgba(0,0,0,.18)">'
                                    . '<span style="font-weight:600">' . e($name) . '</span></span>'
                                ),
                            ])->all())
                            ->columns(2)
                            ->helperText(fn () => $record->dishPhotos() ? null : 'No dish in this category has a photo yet. Add photos in Menu Items first.'),
                    ])
                    ->action(function (Category $record, array $data) {
                        $ok = $record->useDishPhoto($data['photo']);
                        \Filament\Notifications\Notification::make()
                            ->title($ok ? 'Category photo updated' : 'Could not copy that photo')
                            ->{$ok ? 'success' : 'danger'}()->send();
                    }),
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
            // Same order as the website: Order number, then name. Drag rows to change it.
            ->defaultSort(fn ($query) => $query->orderBy('sort_order')->orderBy('name'))
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
