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
    use \App\Filament\Concerns\HasRoleAccess;

    protected static function roleAccess(): array
    {
        return ['view' => ['owner', 'manager', 'staff'], 'edit' => ['owner', 'manager'], 'delete' => ['owner', 'manager']];
    }

    protected static ?string $model = MenuItem::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Menu Items';
    protected static ?string $navigationGroup = 'Menu';
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
                    ->label('Food Images (Multiple photos allowed)')
                    ->image()
                    ->disk('public')
                    ->directory('menu-items')
                    ->visibility('public')
                    ->multiple()
                    ->reorderable()
                    ->appendFiles()
                    ->openable()
                    ->downloadable()
                    ->maxSize(10240)
                    ->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('Pricing & Time')->schema([
                Forms\Components\TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('₹')
                    ->minValue(0),
                Forms\Components\TextInput::make('quantity_label')
                    ->label('Quantity / portion (optional)')
                    ->placeholder('e.g. 250 g, 4 pieces, Serves 2')
                    ->helperText('Shown to customers with the dish. Leave empty to hide.')
                    ->maxLength(60)
                    ->datalist(['250 g', '500 g', '1 kg', '2 pieces', '4 pieces', '6 pieces', 'Half', 'Full', 'Serves 1', 'Serves 2', '500 ml', '1 plate']),
                Forms\Components\TextInput::make('prep_time')
                    ->numeric()
                    ->suffix('min')
                    ->default(20),
                Forms\Components\TextInput::make('rating')
                    ->required()
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

            Forms\Components\Section::make('Goes well with (optional)')
                ->description('Pick dishes to suggest on this dish\'s page. Leave empty and the website picks automatically from what customers order together and from your sides (breads, papad…).')
                ->collapsible()
                ->collapsed(fn ($record) => empty($record?->pairs_with))
                ->schema([
                    Forms\Components\Select::make('pairs_with')
                        ->hiddenLabel()
                        ->multiple()
                        ->maxItems(6)
                        ->searchable()
                        ->preload()
                        ->options(fn ($record) => MenuItem::query()
                            ->when($record, fn ($q) => $q->whereKeyNot($record->id))
                            ->orderBy('name')->pluck('name', 'id')->all())
                        ->placeholder('Search dishes, e.g. Butter Naan, Masala Papad'),
                ]),

            Forms\Components\Section::make('Choices / options (optional)')
                ->description('Let customers choose, e.g. "Choose bread: 2 Butter Roti / 1 Butter Naan (+₹10) / 1 Laccha Paratha (+₹20)". The dish price above is the starting price; each choice can add extra. Leave empty for a normal dish.')
                ->collapsible()
                ->collapsed(fn ($record) => empty($record?->option_groups) && empty($record?->customisation_ids))
                ->schema([
                    Forms\Components\Select::make('customisation_ids')
                        ->label('Saved customisations')
                        ->helperText('Pick from Menu → Customisations. Made once, used on many dishes — editing it there updates all of them. Use "+" to create a new one here.')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(fn () => \App\Models\Customisation::query()->orderBy('sort_order')->orderBy('name')->get()
                            ->mapWithKeys(fn ($c) => [$c->id => $c->label() . ($c->is_active ? '' : ' (not in use)')])->all())
                        ->getOptionLabelsUsing(fn (array $values) => \App\Models\Customisation::whereIn('id', $values)->get()->mapWithKeys(fn ($c) => [$c->id => $c->label()])->all())
                        ->dehydrateStateUsing(fn ($state) => $state ? array_values(array_map('intval', (array) $state)) : null)
                        ->createOptionForm(\App\Filament\Resources\CustomisationResource::fields())
                        ->createOptionUsing(fn (array $data) => \App\Models\Customisation::create($data + ['is_active' => true])->id)
                        ->createOptionModalHeading('New customisation')
                        ->columnSpanFull(),
                    Forms\Components\Repeater::make('option_groups')
                        ->label('Extra options only for this dish (optional)')
                        ->addActionLabel('+ Add option group')
                        ->reorderable()
                        ->collapsible()
                        ->cloneable()
                        ->defaultItems(0)
                        ->itemLabel(fn (array $state): ?string => ($state['name'] ?? null)
                            ? $state['name'] . ' · ' . count($state['choices'] ?? []) . ' choices'
                            : 'New option group')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Group name')
                                ->placeholder('e.g. Choose bread, Choose sabji, Add-ons')
                                ->required()
                                ->maxLength(80)
                                ->distinct(),
                            Forms\Components\Select::make('type')
                                ->label('Customer can pick')
                                ->options(['single' => 'Only 1 choice', 'multiple' => 'Many choices'])
                                ->default('single')
                                ->native(false)
                                ->live()
                                ->required(),
                            Forms\Components\Toggle::make('required')
                                ->label('Must choose')
                                ->helperText('Customer cannot add the dish without picking')
                                ->default(true)
                                ->inline(false),
                            Forms\Components\TextInput::make('max')
                                ->label('Max picks (optional)')
                                ->numeric()
                                ->minValue(1)
                                ->visible(fn (Forms\Get $get) => $get('type') === 'multiple'),
                            Forms\Components\Repeater::make('choices')
                                ->label('Choices')
                                ->addActionLabel('+ Add choice')
                                ->reorderable()
                                ->minItems(1)
                                ->defaultItems(2)
                                ->columns(3)
                                ->columnSpanFull()
                                ->schema([
                                    Forms\Components\TextInput::make('name')
                                        ->label('Choice')
                                        ->placeholder('e.g. 1 Butter Naan')
                                        ->required()
                                        ->maxLength(80)
                                        ->distinct(),
                                    Forms\Components\TextInput::make('price')
                                        ->label('Extra price')
                                        ->numeric()
                                        ->prefix('+₹')
                                        ->minValue(0)
                                        ->default(0),
                                    Forms\Components\Toggle::make('available')
                                        ->label('Available')
                                        ->default(true)
                                        ->inline(false),
                                ]),
                        ])
                        ->columns(4),
                ]),
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
                    ->circular()
                    ->stacked()
                    ->limit(1)
                    ->size(44),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->wrap()
                    // quantity + options shown under the name instead of separate columns
                    ->description(fn (MenuItem $record) => collect([
                        $record->quantity_label,
                        count($record->groups()) ? count($record->groups()) . ' option group(s)' : null,
                    ])->filter()->implode(' · ') ?: null),

                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(function (string $state): string {
                        static $cats = null; // load categories once, not once per row
                        $cats ??= \App\Models\Category::all()->keyBy('slug');
                        $cat = $cats[$state] ?? null;
                        return $cat ? (($cat->emoji ? $cat->emoji . ' ' : '') . $cat->name) : $state;
                    })
                    ->color('gray')
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('price')
                    ->formatStateUsing(fn ($state) => '₹' . number_format($state, 0))
                    ->sortable(),

                Tables\Columns\TextColumn::make('option_groups')
                    ->label('Options')
                    ->state(fn (MenuItem $record) => count($record->groups()) ? count($record->groups()) . ' group(s)' : null)
                    ->badge()
                    ->color('warning')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('quantity_label')
                    ->label('Quantity')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('prep_time')
                    ->suffix(' min')
                    ->label('Prep Time')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('rating')
                    ->formatStateUsing(fn ($state) => '★ ' . $state)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\ToggleColumn::make('is_available')
                    ->label('Available')
                    ->onColor('success'),

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
            // Same order as the website: category order, then dish order. Filter one category, then drag to reorder its dishes.
            ->defaultSort(fn ($query) => $query->menuOrder())
            ->reorderable('sort_order');
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
