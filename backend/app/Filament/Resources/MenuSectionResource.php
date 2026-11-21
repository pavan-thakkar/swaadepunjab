<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuSectionResource\Pages;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Support\TimeWindow;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** Admin → Menu → Timed Menus: e.g. "Breakfast" 4–7 PM with picked dishes. */
class MenuSectionResource extends Resource
{
    use \App\Filament\Concerns\HasRoleAccess;

    protected static function roleAccess(): array
    {
        return ['view' => ['owner', 'manager'], 'edit' => ['owner', 'manager'], 'delete' => ['owner', 'manager']];
    }

    protected static ?string $model = MenuSection::class;
    protected static ?string $slug = 'timed-menus';

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Timed Menus';
    protected static ?string $modelLabel = 'timed menu';
    protected static ?string $pluralModelLabel = 'Timed Menus';
    protected static ?string $navigationGroup = 'Menu';
    protected static ?int $navigationSort = 3;

    private static function times(): array
    {
        $out = [];
        for ($m = 0; $m < 24 * 60; $m += 30) {
            $hm = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
            $out[$hm] = TimeWindow::format($hm);
        }
        return $out;
    }

    /** Dish picker options: "Masala Maggi — ₹80 · Snacks" (search by name). */
    private static function dishOptions(?string $search = null): array
    {
        $q = mb_strtolower(trim((string) $search));
        $cats = \App\Models\Category::pluck('name', 'slug');
        return MenuItem::query()
            ->when($q !== '', fn ($w) => $w->whereRaw('LOWER(name) LIKE ?', ['%' . $q . '%']))
            ->orderBy('name')
            ->limit($q !== '' ? 50 : 500)
            ->get(['id', 'name', 'price', 'category', 'is_available'])
            ->mapWithKeys(fn ($d) => [$d->id => $d->name . ' — ₹' . number_format((float) $d->price, 0)
                . ' · ' . ($cats[$d->category] ?? $d->category) . ($d->is_available ? '' : ' (sold out)')])
            ->all();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Section')
                ->description('Shows on the website home page only during these hours, above the full menu.')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->required()->maxLength(60)
                        ->placeholder('e.g. Breakfast, Evening Snacks, Late Night'),
                    Forms\Components\TextInput::make('emoji')
                        ->label('Icon (optional)')->maxLength(8)
                        ->placeholder('☕'),
                    Forms\Components\TextInput::make('subtitle')
                        ->label('Short line (optional)')->maxLength(120)
                        ->placeholder('e.g. Hot Maggi, sandwiches & chai — fresh every evening')
                        ->columnSpanFull(),
                    Forms\Components\Select::make('available_from')
                        ->label('Show from')->options(self::times())->native(false)->required()->default('16:00'),
                    Forms\Components\Select::make('available_until')
                        ->label('Until')->options(self::times())->native(false)->required()->default('19:00')
                        ->different('available_from')
                        ->helperText('Late-night works too, e.g. 10 PM → 2 AM.'),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Switched on')->default(true),
                ])->columns(2),

            Forms\Components\Section::make('Dishes in this section')
                ->schema([
                    Forms\Components\Select::make('menu_item_ids')
                        ->label('Search and add dishes')
                        ->multiple()
                        ->searchable()
                        ->options(fn () => self::dishOptions())
                        ->getSearchResultsUsing(fn (string $search) => self::dishOptions($search))
                        ->getOptionLabelsUsing(fn (array $values) => collect(self::dishOptions())->only($values)->all())
                        ->placeholder('Type a dish name, e.g. Maggi, Sandwich')
                        ->helperText('They show in this order on the website. Sold-out dishes are skipped automatically.')
                        ->required(),
                ]),

            Forms\Components\Section::make('Selling these dishes')
                ->schema([
                    Forms\Components\Toggle::make('sell_only_in_time')
                        ->label('Sell these dishes ONLY during this time')
                        ->helperText('Off = the dishes stay in the full menu all day; this section is just a highlight during these hours.')
                        ->live(),
                    Forms\Components\Radio::make('outside_hours')
                        ->label('Outside these hours, in the full menu')
                        ->options([
                            'show' => 'Show them as "Available 4 PM – 7 PM" (customers can\'t add)',
                            'hide' => 'Hide them',
                        ])
                        ->default('show')
                        ->dehydrateStateUsing(fn ($state) => in_array($state, ['show', 'hide'], true) ? $state : 'show')
                        ->visible(fn (Forms\Get $get) => (bool) $get('sell_only_in_time')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->weight('bold')
                    ->formatStateUsing(fn ($state, MenuSection $r) => trim(($r->emoji ? $r->emoji . ' ' : '') . $state))
                    ->description(fn (MenuSection $r) => $r->subtitle),
                Tables\Columns\TextColumn::make('available_from')
                    ->label('Time')
                    ->formatStateUsing(fn ($state, MenuSection $r) => $r->label()),
                Tables\Columns\TextColumn::make('live')
                    ->label('Right now')
                    ->badge()
                    ->state(fn (MenuSection $r) => ! $r->is_active ? 'off' : ($r->isOpen() ? 'live' : 'later'))
                    ->formatStateUsing(fn ($state) => ['live' => '🟢 Showing now', 'later' => '⏳ Not now', 'off' => 'Switched off'][$state] ?? $state)
                    ->color(fn ($state) => ['live' => 'success', 'later' => 'warning', 'off' => 'gray'][$state] ?? 'gray'),
                Tables\Columns\TextColumn::make('menu_item_ids')
                    ->label('Dishes')
                    ->formatStateUsing(fn ($state, MenuSection $r) => count($r->dishIds()) . ' dishes')
                    ->description(function (MenuSection $r) {
                        $first = array_slice($r->dishIds(), 0, 4);
                        $names = MenuItem::whereIn('id', $first)->pluck('name', 'id');
                        return collect($first)->map(fn ($id) => $names[$id] ?? null)->filter()->implode(', ') . (count($r->dishIds()) > 4 ? '…' : '');
                    })
                    ->wrap(),
                Tables\Columns\IconColumn::make('sell_only_in_time')
                    ->label('Only in time')
                    ->boolean(),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('On'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('No timed menus yet')
            ->emptyStateDescription('Create one, e.g. "Breakfast" from 4 PM to 7 PM, and add Maggi, sandwiches, chai…');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMenuSections::route('/'),
            'create' => Pages\CreateMenuSection::route('/create'),
            'edit'   => Pages\EditMenuSection::route('/{record}/edit'),
        ];
    }
}
