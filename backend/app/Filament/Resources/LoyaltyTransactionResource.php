<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoyaltyTransactionResource\Pages;
use App\Models\LoyaltyTransaction;
use App\Services\Loyalty;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LoyaltyTransactionResource extends Resource
{
    use \App\Filament\Concerns\HasRoleAccess;

    protected static function roleAccess(): array
    {
        return ['view' => ['owner', 'manager'], 'edit' => ['owner', 'manager'], 'delete' => ['owner']];
    }

    protected static ?string $model = LoyaltyTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';
    protected static ?string $navigationLabel = 'Loyalty Points';
    protected static ?string $modelLabel = 'points entry';
    protected static ?string $pluralModelLabel = 'Loyalty Points';
    protected static ?string $navigationGroup = 'Grow';
    protected static ?int $navigationSort = 3;

    /** Used for manual adjustments only. */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('phone')
                ->label('Customer Phone')
                ->tel()
                ->required()
                ->live(debounce: 600)
                ->helperText(fn (Forms\Get $get) => $get('phone') ? 'Current balance: ' . Loyalty::balance($get('phone')) . ' points' : 'Enter the 10-digit phone number'),
            Forms\Components\TextInput::make('points')
                ->label('Points to add (use minus to remove, e.g. -50)')
                ->numeric()->integer()->required()
                ->rule('not_in:0'),
            Forms\Components\TextInput::make('note')
                ->label('Reason')
                ->placeholder('e.g. Birthday bonus, complaint compensation')
                ->required()
                ->maxLength(200)
                ->columnSpanFull(),
            Forms\Components\Hidden::make('type')->default('adjust'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Date')->dateTime('d M Y, h:i A')->sortable(),
                Tables\Columns\TextColumn::make('phone')->label('Phone')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('points')
                    ->label('Points')
                    ->formatStateUsing(fn ($state) => ($state > 0 ? '+' : '') . $state)
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('type')->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'earn' => 'Earned', 'redeem' => 'Used', 'refund' => 'Returned', 'reverse' => 'Removed', 'adjust' => 'Manual', default => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'earn', 'refund' => 'success', 'redeem', 'reverse' => 'danger', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('note')->label('Details')->wrap(),
                Tables\Columns\TextColumn::make('balance')
                    ->label('Customer Balance Now')
                    ->state(fn (LoyaltyTransaction $r) => Loyalty::balance($r->phone) . ' pts'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options([
                    'earn' => 'Earned', 'redeem' => 'Used', 'refund' => 'Returned', 'reverse' => 'Removed', 'adjust' => 'Manual',
                ]),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListLoyaltyTransactions::route('/'),
            'create' => Pages\CreateLoyaltyTransaction::route('/create'),
        ];
    }
}
