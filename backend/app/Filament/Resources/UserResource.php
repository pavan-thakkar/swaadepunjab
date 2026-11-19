<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    use \App\Filament\Concerns\HasRoleAccess; // owner only (default)

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Staff Accounts';
    protected static ?string $modelLabel = 'staff account';
    protected static ?string $pluralModelLabel = 'Staff Accounts';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 90;

    public static function form(Form $form): Form
    {
        $isSelf = fn (?User $record) => $record && $record->id === auth()->id();

        return $form->schema([
            Forms\Components\Section::make('Login details')->schema([
                Forms\Components\TextInput::make('name')->label('Name')->required()->maxLength(100),
                Forms\Components\TextInput::make('email')->label('Login email')->email()->required()
                    ->unique(ignoreRecord: true)->maxLength(150),
                Forms\Components\TextInput::make('password')
                    ->label(fn (string $operation) => $operation === 'create' ? 'Password' : 'New password (leave blank to keep)')
                    ->password()->revealable()
                    ->rule(\Illuminate\Validation\Rules\Password::min(8))
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn ($state) => filled($state))
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state)),
            ])->columns(3),

            Forms\Components\Section::make('Access')->schema([
                Forms\Components\Radio::make('role')
                    ->label('Role')
                    ->options(User::ROLES)
                    ->descriptions([
                        'owner'   => 'Everything: sales reports, settings, staff accounts, activity log, delete orders.',
                        'manager' => 'Orders, menu, categories, offers, coupons, cards, reviews, customers, loyalty, WhatsApp. No reports, settings or staff accounts.',
                        'staff'   => 'Counter: new-order alarm, orders (accept, status, print bill, counter orders) and switching dishes available / sold out. No sales totals or customer list.',
                    ])
                    ->default('staff')
                    ->required()
                    ->disabled($isSelf)
                    ->helperText(fn (?User $record) => $record && $record->id === auth()->id() ? 'You cannot change your own role.' : null),
                Forms\Components\Toggle::make('is_active')
                    ->label('Account is active (can log in)')
                    ->default(true)
                    ->disabled($isSelf),
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
            ->defaultSort('created_at')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->weight('bold')
                    ->description(fn (User $r) => $r->id === auth()->id() ? 'You' : null),
                Tables\Columns\TextColumn::make('email')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('role')->badge()
                    ->formatStateUsing(fn ($state) => ['owner' => 'Owner', 'manager' => 'Manager', 'staff' => 'Counter Staff'][$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'owner' => 'warning', 'manager' => 'info', default => 'gray' }),
                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
                Tables\Columns\TextColumn::make('last_login_at')->label('Last login')->since()->placeholder('Never'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (User $r) => $r->id !== auth()->id())
                    ->before(function (User $r, Tables\Actions\DeleteAction $action) {
                        if ($r->role === 'owner' && User::where('role', 'owner')->where('is_active', true)->count() <= 1) {
                            \Filament\Notifications\Notification::make()->title('You need at least one active Owner.')->danger()->send();
                            $action->cancel();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
