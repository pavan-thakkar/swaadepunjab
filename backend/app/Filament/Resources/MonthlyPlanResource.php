<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MonthlyPlanResource\Pages;
use App\Models\MonthlyPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MonthlyPlanResource extends Resource
{
    use \App\Filament\Concerns\HasRoleAccess;

    protected static function roleAccess(): array
    {
        return ['view' => ['owner', 'manager'], 'edit' => ['owner', 'manager'], 'delete' => ['owner']];
    }

    protected static ?string $model = MonthlyPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Monthly Plans';
    protected static ?string $modelLabel = 'monthly plan';
    protected static ?string $navigationGroup = 'Grow';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Plan')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Plan name')
                    ->placeholder('e.g. Office Lunch Thali')
                    ->required()
                    ->maxLength(120),
                Forms\Components\TextInput::make('badge')
                    ->label('Badge (optional)')
                    ->placeholder('e.g. Most popular, Best value')
                    ->maxLength(40),
                Forms\Components\Textarea::make('description')
                    ->label('Short description')
                    ->placeholder('e.g. A full Punjabi thali delivered hot to your office every working day.')
                    ->rows(2)
                    ->maxLength(500)
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('image')
                    ->label('Photo (optional)')
                    ->image()
                    ->disk('public')
                    ->directory('monthly-plans')
                    ->visibility('public')
                    ->maxSize(10240)
                    ->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('Price & schedule')->schema([
                Forms\Components\TextInput::make('price')
                    ->label('Price')
                    ->numeric()
                    ->prefix('₹')
                    ->minValue(0)
                    ->required(),
                Forms\Components\TextInput::make('price_note')
                    ->label('Price is…')
                    ->default('per person / month')
                    ->datalist(['per person / month', 'per month', 'per meal', 'per team / month'])
                    ->required()
                    ->maxLength(60),
                Forms\Components\Select::make('meal_type')
                    ->label('Meals')
                    ->options(MonthlyPlan::MEAL_TYPES)
                    ->default('lunch')
                    ->native(false)
                    ->required(),
                Forms\Components\TextInput::make('days_per_month')
                    ->label('Days per month (optional)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(31)
                    ->placeholder('e.g. 22 (Mon–Fri)'),
                Forms\Components\TextInput::make('min_people')
                    ->label('Minimum people (optional)')
                    ->numeric()
                    ->minValue(1)
                    ->placeholder('e.g. 10'),
            ])->columns(3),

            Forms\Components\Section::make("What's included")
                ->description('One line each, e.g. "Dal Makhani or Dal Tadka", "1 Paneer / seasonal sabji", "4 Butter Roti", "Jeera Rice", "Salad & pickle".')
                ->schema([
                    Forms\Components\TagsInput::make('includes')
                        ->hiddenLabel()
                        ->placeholder('Type an item and press Enter')
                        ->reorderable()
                        ->splitKeys(['Enter', 'Tab']),
                ]),

            Forms\Components\Section::make('Visibility')->schema([
                Forms\Components\Toggle::make('is_active')
                    ->label('Show this plan on the website')
                    ->default(true),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Order (lower shows first)')
                    ->numeric()
                    ->default(0),
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
                Tables\Columns\TextColumn::make('name')->searchable()->weight('bold')
                    ->description(fn (MonthlyPlan $r) => $r->badge),
                Tables\Columns\TextColumn::make('price')
                    ->formatStateUsing(fn ($state, MonthlyPlan $r) => '₹' . number_format((float) $state, 0) . ' ' . $r->price_note)
                    ->sortable(),
                Tables\Columns\TextColumn::make('meal_type')->label('Meals')
                    ->formatStateUsing(fn ($state) => MonthlyPlan::MEAL_TYPES[$state] ?? $state),
                Tables\Columns\TextColumn::make('days_per_month')->label('Days')->placeholder('—'),
                Tables\Columns\TextColumn::make('enquiries_count')->counts('enquiries')->label('Enquiries')->badge(),
                Tables\Columns\ToggleColumn::make('is_active')->label('On website'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ReplicateAction::make()->label('Copy')
                    ->beforeReplicaSaved(fn (MonthlyPlan $replica) => $replica->fill(['name' => $replica->name . ' (copy)', 'is_active' => false])),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('No monthly plans yet')
            ->emptyStateDescription('Create a plan, then switch "Show Monthly Plans on website" ON at the top.');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMonthlyPlans::route('/'),
            'create' => Pages\CreateMonthlyPlan::route('/create'),
            'edit'   => Pages\EditMonthlyPlan::route('/{record}/edit'),
        ];
    }
}
