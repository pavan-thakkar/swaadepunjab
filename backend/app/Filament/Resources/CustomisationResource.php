<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomisationResource\Pages;
use App\Models\Customisation;
use App\Models\MenuItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** Reusable choice groups ("Choose bread", "Add-ons") — make once, use on many dishes. */
class CustomisationResource extends Resource
{
    use \App\Filament\Concerns\HasRoleAccess;

    protected static function roleAccess(): array
    {
        return ['view' => ['owner', 'manager'], 'edit' => ['owner', 'manager'], 'delete' => ['owner', 'manager']];
    }

    protected static ?string $model = Customisation::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationLabel = 'Customisations';
    protected static ?string $modelLabel = 'customisation';
    protected static ?string $navigationGroup = 'Menu';
    protected static ?int $navigationSort = 3;

    /** The form fields, also used for "create new" right inside a dish. */
    public static function fields(): array
    {
        return [
            Forms\Components\TextInput::make('name')
                ->label('Name customers see')
                ->placeholder('e.g. Choose bread, Choose sabji, Add-ons')
                ->required()
                ->maxLength(80),
            Forms\Components\TextInput::make('admin_label')
                ->label('Note for admins (optional)')
                ->placeholder('e.g. Breads for dal combos')
                ->helperText('Helps you tell similar ones apart. Customers don\'t see it.')
                ->maxLength(80),
            Forms\Components\Select::make('type')
                ->label('Customer can pick')
                ->options(['single' => 'Only 1 choice', 'multiple' => 'Many choices'])
                ->default('single')
                ->native(false)
                ->live()
                ->required(),
            Forms\Components\Toggle::make('required')
                ->label('Must choose')
                ->default(true)
                ->inline(false),
            Forms\Components\TextInput::make('max')
                ->label('Max picks (optional)')
                ->numeric()->minValue(1)
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
                    Forms\Components\TextInput::make('name')->label('Choice')->placeholder('e.g. 1 Butter Naan')->required()->maxLength(80)->distinct(),
                    Forms\Components\TextInput::make('price')->label('Extra price')->numeric()->prefix('+₹')->minValue(0)->default(0),
                    Forms\Components\Toggle::make('available')->label('Available')->default(true)->inline(false),
                ]),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Customisation')
                ->description('Changes here update every dish that uses this customisation.')
                ->schema(static::fields())
                ->columns(2),
            Forms\Components\Section::make('Visibility')->schema([
                Forms\Components\Toggle::make('is_active')->label('In use (shown on dishes)')->default(true),
                Forms\Components\TextInput::make('sort_order')->label('Order (lower first)')->numeric()->default(0)
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? $state : 0),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(['name', 'admin_label'])->weight('bold')
                    ->description(fn (Customisation $r) => $r->admin_label),
                Tables\Columns\TextColumn::make('choices')->label('Choices')
                    ->state(fn (Customisation $r) => collect($r->choices ?? [])->map(function ($c) {
                        $p = (float) ($c['price'] ?? 0);
                        return ($c['name'] ?? '') . ($p > 0 ? ' (+₹' . number_format($p, 0) . ')' : '') . (($c['available'] ?? true) ? '' : ' ✕');
                    })->implode(', '))
                    ->wrap()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('type')->label('Pick')
                    ->formatStateUsing(fn ($state, Customisation $r) => ($state === 'multiple' ? 'Many' : '1') . ($r->required ? ' · must' : ' · optional'))
                    ->badge()->color('warning'),
                Tables\Columns\TextColumn::make('used_by')->label('Used on')
                    ->state(fn (Customisation $r) => ($n = $r->dishes()->count()) ? $n . ' dish' . ($n === 1 ? '' : 'es') : 'Not used yet')
                    ->tooltip(fn (Customisation $r) => $r->dishes()->pluck('name')->take(15)->implode(', ') ?: null),
                Tables\Columns\ToggleColumn::make('is_active')->label('In use'),
            ])
            ->actions([
                Tables\Actions\Action::make('attach')
                    ->label('Add to dishes')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->modalHeading(fn (Customisation $r) => 'Add "' . $r->name . '" to dishes')
                    ->modalDescription('Pick dishes one by one, or a whole category.')
                    ->form([
                        Forms\Components\Select::make('categories')
                            ->label('Whole categories')
                            ->multiple()
                            ->options(fn () => \App\Models\Category::orderBy('sort_order')->pluck('name', 'slug')->all()),
                        Forms\Components\Select::make('dishes')
                            ->label('Dishes')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(fn () => MenuItem::orderBy('name')->pluck('name', 'id')->all()),
                    ])
                    ->action(function (Customisation $r, array $data) {
                        $q = MenuItem::query();
                        $ids = array_map('intval', $data['dishes'] ?? []);
                        $cats = $data['categories'] ?? [];
                        if (! $ids && ! $cats) {
                            Notification::make()->title('Pick at least one dish or category')->warning()->send();
                            return;
                        }
                        $q->where(fn ($w) => $w->whereIn('id', $ids ?: [0])->orWhereIn('category', $cats ?: ['__none__']));
                        $n = 0;
                        foreach ($q->get() as $dish) {
                            $cur = array_map('intval', (array) ($dish->customisation_ids ?? []));
                            if (! in_array($r->id, $cur, true)) {
                                $cur[] = $r->id;
                                $dish->update(['customisation_ids' => array_values($cur)]);
                                $n++;
                            }
                        }
                        Notification::make()->title("Added to {$n} dish" . ($n === 1 ? '' : 'es'))->success()->send();
                    }),
                Tables\Actions\Action::make('detach')
                    ->label('Remove from dishes')
                    ->icon('heroicon-o-minus-circle')
                    ->color('gray')
                    ->visible(fn (Customisation $r) => $r->dishes()->isNotEmpty())
                    ->form(fn (Customisation $r) => [
                        Forms\Components\CheckboxList::make('dishes')
                            ->label('Remove from')
                            ->options($r->dishes()->pluck('name', 'id')->all())
                            ->bulkToggleable()
                            ->columns(2),
                    ])
                    ->action(function (Customisation $r, array $data) {
                        $n = 0;
                        foreach (MenuItem::whereIn('id', array_map('intval', $data['dishes'] ?? []))->get() as $dish) {
                            $cur = array_values(array_filter(array_map('intval', (array) ($dish->customisation_ids ?? [])), fn ($id) => $id !== $r->id));
                            $dish->update(['customisation_ids' => $cur ?: null]);
                            $n++;
                        }
                        Notification::make()->title("Removed from {$n} dish" . ($n === 1 ? '' : 'es'))->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\ReplicateAction::make()->label('Copy')
                    ->beforeReplicaSaved(fn (Customisation $replica) => $replica->fill(['name' => $replica->name, 'admin_label' => trim(($replica->admin_label ?? '') . ' (copy)')])),
                Tables\Actions\DeleteAction::make()
                    ->modalDescription('It will be removed from every dish that uses it.')
                    ->after(function (Customisation $record) {
                        foreach (MenuItem::whereNotNull('customisation_ids')->get() as $dish) {
                            $cur = array_map('intval', (array) $dish->customisation_ids);
                            if (in_array($record->id, $cur, true)) {
                                $dish->update(['customisation_ids' => array_values(array_diff($cur, [$record->id])) ?: null]);
                            }
                        }
                    }),
            ])
            ->emptyStateHeading('No customisations yet')
            ->emptyStateDescription('Create one, e.g. "Choose bread" with 2 Butter Roti / 1 Butter Naan (+₹10) / 1 Laccha Paratha (+₹20), then add it to dishes.');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCustomisations::route('/'),
            'create' => Pages\CreateCustomisation::route('/create'),
            'edit'   => Pages\EditCustomisation::route('/{record}/edit'),
        ];
    }
}
