<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use App\Models\ActivityLog;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ActivityLogResource extends Resource
{
    use \App\Filament\Concerns\HasRoleAccess; // owner only

    protected static ?string $model = ActivityLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Staff Activity';
    protected static ?string $pluralModelLabel = 'Staff Activity';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 91;

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            // Remember filters, search and sorting when the admin leaves and comes back
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->defaultSort('created_at', 'desc')
            ->poll('30s')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('When')->dateTime('d M, h:i A')->sortable()
                    ->description(fn (ActivityLog $r) => $r->created_at?->diffForHumans()),
                Tables\Columns\TextColumn::make('user_name')->label('Who')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('action')->badge()
                    ->formatStateUsing(fn ($state) => ['login' => 'Login', 'login_failed' => 'Failed login', 'created' => 'Added', 'updated' => 'Changed', 'deleted' => 'Deleted', 'status' => 'Order status'][$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'deleted', 'login_failed' => 'danger', 'created' => 'success', 'status' => 'warning', 'login' => 'gray', default => 'info' }),
                Tables\Columns\TextColumn::make('description')->label('What happened')->wrap()->searchable(),
                Tables\Columns\TextColumn::make('ip')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')->label('Person')->relationship('user', 'name'),
                Tables\Filters\SelectFilter::make('action')->options([
                    'login' => 'Logins', 'login_failed' => 'Failed logins', 'status' => 'Order status changes',
                    'created' => 'Added', 'updated' => 'Changed', 'deleted' => 'Deleted',
                ]),
                Tables\Filters\SelectFilter::make('subject_type')->label('Area')->options([
                    'Order' => 'Orders', 'MenuItem' => 'Menu items', 'Offer' => 'Offers', 'Coupon' => 'Coupons',
                    'ReceiptSetting' => 'Store settings', 'User' => 'Staff accounts', 'LoyaltyTransaction' => 'Loyalty points',
                ]),
                Tables\Filters\Filter::make('today')->label('Today only')->query(fn ($query) => $query->whereDate('created_at', today())),
            ])
            ->actions([Tables\Actions\ViewAction::make()]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\TextEntry::make('created_at')->label('When')->dateTime('d M Y, h:i:s A'),
            Infolists\Components\TextEntry::make('user_name')->label('Who'),
            Infolists\Components\TextEntry::make('description')->label('What happened')->columnSpanFull(),
            Infolists\Components\KeyValueEntry::make('changes')->label('Changes')
                ->state(fn (ActivityLog $r) => collect($r->changes ?? [])->mapWithKeys(fn ($c, $f) => [str_replace('_', ' ', $f) => (is_array($c) ? (($c['from'] ?? '—') . '  →  ' . ($c['to'] ?? '—')) : (string) $c)])->all())
                ->columnSpanFull(),
            Infolists\Components\TextEntry::make('ip')->label('IP address'),
        ])->columns(2);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
        ];
    }
}
