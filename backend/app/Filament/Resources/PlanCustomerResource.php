<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanCustomerResource\Pages;
use App\Models\PlanEnquiry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Confirmed monthly-plan customers (plan enquiries with status = active). */
class PlanCustomerResource extends Resource
{
    use \App\Filament\Concerns\HasRoleAccess;

    protected static function roleAccess(): array
    {
        return ['view' => ['owner', 'manager'], 'edit' => ['owner', 'manager'], 'delete' => ['owner']];
    }

    protected static ?string $model = PlanEnquiry::class;
    protected static ?string $slug = 'plan-customers';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Active Plan Customers';
    protected static ?string $modelLabel = 'plan customer';
    protected static ?string $pluralModelLabel = 'active plan customers';
    protected static ?string $navigationGroup = 'Grow';
    protected static ?int $navigationSort = 7;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('status', 'active');
    }

    // Red number = plans ending soon that nobody has called about yet
    public static function getNavigationBadge(): ?string
    {
        $n = PlanEnquiry::needsCallCount();
        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Plans ending soon — call to renew';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->defaultSort('active_until', 'asc')
            ->recordUrl(fn (PlanEnquiry $r) => PlanEnquiryResource::getUrl('edit', ['record' => $r]))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Customer')->searchable(['name', 'company', 'phone'])->weight('bold')
                    ->description(fn (PlanEnquiry $r) => $r->company),
                Tables\Columns\TextColumn::make('phone')->url(fn (PlanEnquiry $r) => 'tel:' . $r->phone)->searchable(),
                Tables\Columns\TextColumn::make('plan_name')->label('Plan')->placeholder('—')
                    ->description(fn (PlanEnquiry $r) => $r->delivery_time),
                Tables\Columns\TextColumn::make('agreed_people')->label('People')->placeholder('—')
                    ->state(fn (PlanEnquiry $r) => $r->agreed_people ?: $r->people),
                Tables\Columns\TextColumn::make('active_from')->label('Period')
                    ->state(fn (PlanEnquiry $r) => $r->active_from && $r->active_until ? $r->active_from->format('d M') . ' – ' . $r->active_until->format('d M Y') : null)
                    ->placeholder('Dates not set'),
                Tables\Columns\TextColumn::make('active_until')->label('Ends')->sortable()
                    ->state(fn (PlanEnquiry $r) => $r->stage() === 'upcoming' ? 'Starts ' . $r->active_from?->format('d M') : $r->endsLabel())
                    ->badge()
                    ->color(fn (PlanEnquiry $r) => match (true) {
                        ! $r->active_until => 'gray',
                        $r->stage() === 'upcoming' => 'info',
                        $r->daysLeft() <= 0 => 'danger',
                        $r->daysLeft() <= PlanEnquiry::alertDays() => 'warning',
                        default => 'success',
                    })
                    ->description(fn (PlanEnquiry $r) => $r->renewal_called_at ? '✓ Called ' . $r->renewal_called_at->format('d M') . ($r->renewal_called_by ? ' by ' . strtok($r->renewal_called_by, ' ') : '') : null),
                Tables\Columns\TextColumn::make('payment_status')->label('Payment')->badge()
                    ->formatStateUsing(function ($state, PlanEnquiry $r) {
                        $label = PlanEnquiry::PAYMENT[$state] ?? $state;
                        $due = $r->agreed_price !== null ? max(0, (float) $r->agreed_price - (float) $r->amount_paid) : 0;
                        return $state !== 'paid' && $due > 0 ? $label . ' · ₹' . number_format($due, 0) . ' due' : $label;
                    })
                    ->color(fn ($state) => $state === 'paid' ? 'success' : 'danger'),
            ])
            ->filters([
                Tables\Filters\Filter::make('ending')->label('Ending soon / just ended')
                    ->query(fn (Builder $q) => $q->endingSoon()),
                Tables\Filters\Filter::make('not_called')->label('Not called yet')
                    ->query(fn (Builder $q) => $q->whereNull('renewal_called_at')),
                Tables\Filters\SelectFilter::make('payment_status')->label('Payment')->options(PlanEnquiry::PAYMENT),
                Tables\Filters\SelectFilter::make('monthly_plan_id')->label('Plan')->relationship('plan', 'name'),
            ])
            ->actions([
                Tables\Actions\Action::make('call')->label('Call')->icon('heroicon-o-phone')->color('gray')
                    ->url(fn (PlanEnquiry $r) => 'tel:' . $r->phone),
                Tables\Actions\Action::make('whatsapp')->label('WhatsApp')->icon('heroicon-o-chat-bubble-left-right')->color('success')
                    ->url(fn (PlanEnquiry $r) => $r->whatsappUrl(true), shouldOpenInNewTab: true),
                PlanEnquiryResource::markCalledAction(),
                PlanEnquiryResource::renewAction(),
                Tables\Actions\Action::make('open')->label('Open')->icon('heroicon-o-pencil-square')
                    ->url(fn (PlanEnquiry $r) => PlanEnquiryResource::getUrl('edit', ['record' => $r])),
            ])
            ->emptyStateHeading('No active plan customers yet')
            ->emptyStateDescription('Open a request in Plan Enquiries and set its status to "Active customer".');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlanCustomers::route('/'),
        ];
    }
}
