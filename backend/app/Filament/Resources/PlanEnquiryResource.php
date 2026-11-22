<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanEnquiryResource\Pages;
use App\Models\PlanEnquiry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlanEnquiryResource extends Resource
{
    use \App\Filament\Concerns\HasRoleAccess;

    protected static function roleAccess(): array
    {
        return ['view' => ['owner', 'manager'], 'edit' => ['owner', 'manager'], 'delete' => ['owner']];
    }

    protected static ?string $model = PlanEnquiry::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';
    protected static ?string $navigationLabel = 'Plan Enquiries';
    protected static ?string $modelLabel = 'plan enquiry';
    protected static ?string $pluralModelLabel = 'plan enquiries';
    protected static ?string $navigationGroup = 'Grow';
    protected static ?int $navigationSort = 6;

    public static function getNavigationBadge(): ?string
    {
        $n = PlanEnquiry::where('status', 'new')->count();
        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false; // enquiries come from the website
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Follow-up')->schema([
                Forms\Components\Select::make('status')
                    ->options(PlanEnquiry::STATUSES)
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                        // Becoming an active customer: suggest dates and people from the request
                        if ($state !== 'active') return;
                        if (! $get('active_from')) $set('active_from', $get('start_date') ?: now()->toDateString());
                        if (! $get('active_until')) $set('active_until', \Illuminate\Support\Carbon::parse($get('active_from'))->addMonthNoOverflow()->subDay()->toDateString());
                        if (! $get('agreed_people') && $get('people')) $set('agreed_people', $get('people'));
                    })
                    ->required(),
                Forms\Components\Textarea::make('admin_notes')
                    ->label('Your notes (only admins see this)')
                    ->placeholder('e.g. Called on 20 Nov, 25 people, starting 1 Dec at ₹3,200 each')
                    ->rows(3),
            ])->columns(2),

            Forms\Components\Section::make('Active plan — the customer sees this on the website')
                ->description('Shown under "My Monthly Plan" when the customer logs in with this phone number or email.')
                ->visible(fn (Forms\Get $get) => $get('status') === 'active')
                ->schema([
                    Forms\Components\DatePicker::make('active_from')->label('Plan starts')->native(false)->displayFormat('d M Y')->required(),
                    Forms\Components\DatePicker::make('active_until')->label('Plan ends')->native(false)->displayFormat('d M Y')
                        ->afterOrEqual('active_from')->required(),
                    Forms\Components\TextInput::make('agreed_people')->label('People')->numeric()->minValue(1),
                    Forms\Components\TextInput::make('agreed_price')->label('Total price for this period')->numeric()->prefix('₹')->minValue(0),
                    Forms\Components\TextInput::make('amount_paid')->label('Amount paid')->numeric()->prefix('₹')->minValue(0)->default(0)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                            $price = (float) $get('agreed_price');
                            $paid = (float) $state;
                            $set('payment_status', $paid <= 0 ? 'pending' : ($price > 0 && $paid >= $price ? 'paid' : 'partial'));
                        })
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? $state : 0), // never save empty into a required column
                    Forms\Components\Select::make('payment_status')->label('Payment')->options(PlanEnquiry::PAYMENT)->native(false)->default('pending')
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? $state : 'pending'), // never save empty into a required column
                    Forms\Components\TextInput::make('delivery_time')->label('Delivery time')->placeholder('e.g. 1:00 PM, Mon–Sat')->maxLength(60),
                    Forms\Components\Textarea::make('customer_note')->label('Message to customer (optional)')
                        ->placeholder('e.g. Sunday closed. Call us to pause for holidays.')->rows(2)->maxLength(500)->columnSpanFull(),
                ])->columns(4),

            Forms\Components\Section::make('What the customer sent')->schema([
                Forms\Components\TextInput::make('plan_name')->label('Plan')->disabled(),
                Forms\Components\TextInput::make('name')->disabled(),
                Forms\Components\TextInput::make('company')->disabled(),
                Forms\Components\TextInput::make('phone')->disabled(),
                Forms\Components\TextInput::make('email')->disabled(),
                Forms\Components\TextInput::make('people')->label('People')->disabled(),
                Forms\Components\DatePicker::make('start_date')->label('Wants to start')->disabled(),
                Forms\Components\Textarea::make('address')->label('Delivery address')->disabled()->columnSpanFull(),
                Forms\Components\Textarea::make('message')->disabled()->columnSpanFull(),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        $wa = fn (PlanEnquiry $r) => $r->whatsappUrl($r->status === 'active');

        return $table
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Received')->since()->sortable()
                    ->tooltip(fn (PlanEnquiry $r) => $r->created_at?->format('d M Y, h:i A')),
                Tables\Columns\TextColumn::make('name')->searchable()->weight('bold')
                    ->description(fn (PlanEnquiry $r) => $r->company),
                Tables\Columns\TextColumn::make('phone')->searchable()
                    ->url(fn (PlanEnquiry $r) => 'tel:' . $r->phone),
                Tables\Columns\TextColumn::make('plan_name')->label('Plan')->placeholder('Not sure yet'),
                Tables\Columns\TextColumn::make('people')->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('start_date')->label('Start')->date('d M')->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('active_until')->label('Plan')
                    ->state(fn (PlanEnquiry $r) => match ($r->stage()) {
                        'active'   => $r->daysLeft() . ' days left',
                        'upcoming' => 'Starts ' . $r->active_from?->format('d M'),
                        'ended'    => 'Ended ' . $r->active_until?->format('d M'),
                        default    => null,
                    })
                    ->description(fn (PlanEnquiry $r) => $r->status === 'active' && $r->active_until ? 'till ' . $r->active_until->format('d M Y') : null)
                    ->badge()
                    ->color(fn (PlanEnquiry $r) => match (true) {
                        $r->stage() === 'ended' => 'danger',
                        $r->stage() === 'active' && $r->daysLeft() <= 5 => 'warning',
                        $r->stage() === 'active' => 'success',
                        default => 'gray',
                    })
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state) => PlanEnquiry::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'new' => 'warning', 'contacted' => 'info', 'active' => 'success', default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(PlanEnquiry::STATUSES),
                Tables\Filters\SelectFilter::make('monthly_plan_id')->label('Plan')->relationship('plan', 'name'),
                Tables\Filters\Filter::make('ending_soon')->label('Ending in 7 days / ended')
                    ->query(fn ($query) => $query->where('status', 'active')->whereNotNull('active_until')->whereDate('active_until', '<=', now()->addDays(7))),
            ])
            ->actions([
                Tables\Actions\Action::make('call')->label('Call')->icon('heroicon-o-phone')->color('gray')
                    ->url(fn (PlanEnquiry $r) => 'tel:' . $r->phone),
                Tables\Actions\Action::make('whatsapp')->label('WhatsApp')->icon('heroicon-o-chat-bubble-left-right')->color('success')
                    ->url($wa, shouldOpenInNewTab: true),
                Tables\Actions\Action::make('contacted')->label('Mark contacted')->icon('heroicon-o-check')
                    ->visible(fn (PlanEnquiry $r) => $r->status === 'new')
                    ->action(fn (PlanEnquiry $r) => $r->update(['status' => 'contacted'])),
                static::markCalledAction(),
                static::renewAction(),
                Tables\Actions\EditAction::make()->label('Open'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No enquiries yet')
            ->emptyStateDescription('When a company asks for a monthly plan on the website, it shows up here.');
    }

    /** Renew an active plan for the next period (used here and on Active Plan Customers). */
    public static function renewAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('renew')->label('Renew')->icon('heroicon-o-arrow-path')->color('warning')
                    ->visible(fn (PlanEnquiry $r) => $r->status === 'active' && $r->active_until)
                    ->modalHeading(fn (PlanEnquiry $r) => 'Renew plan for ' . $r->name)
                    ->fillForm(function (PlanEnquiry $r) {
                        $from = $r->active_until->copy()->addDay();
                        if ($from->lt(now()->startOfDay())) $from = now()->startOfDay();
                        return [
                            'active_from'  => $from->toDateString(),
                            'active_until' => $from->copy()->addMonthNoOverflow()->subDay()->toDateString(),
                            'agreed_price' => $r->agreed_price,
                            'amount_paid'  => 0,
                        ];
                    })
                    ->form([
                        Forms\Components\DatePicker::make('active_from')->label('New period starts')->native(false)->required(),
                        Forms\Components\DatePicker::make('active_until')->label('New period ends')->native(false)->afterOrEqual('active_from')->required(),
                        Forms\Components\TextInput::make('agreed_price')->label('Price for new period')->numeric()->prefix('₹'),
                        Forms\Components\TextInput::make('amount_paid')->label('Paid now')->numeric()->prefix('₹')->default(0),
                    ])
                    ->action(function (PlanEnquiry $r, array $data) {
                        $price = (float) ($data['agreed_price'] ?? 0);
                        $paid = (float) ($data['amount_paid'] ?? 0);
                        $r->update([
                            'active_from'    => $data['active_from'],
                            'active_until'   => $data['active_until'],
                            'agreed_price'   => $data['agreed_price'] ?? null,
                            'amount_paid'    => $paid,
                            'payment_status' => $paid <= 0 ? 'pending' : ($price > 0 && $paid >= $price ? 'paid' : 'partial'),
                            'renewal_called_at' => null,
                        'renewal_called_by' => null,
                        'admin_notes'    => trim(($r->admin_notes ? $r->admin_notes . "\n" : '') . 'Renewed ' . now()->format('d M Y') . ': ' . \Illuminate\Support\Carbon::parse($data['active_from'])->format('d M') . ' – ' . \Illuminate\Support\Carbon::parse($data['active_until'])->format('d M Y')),
                        ]);
                        \Filament\Notifications\Notification::make()->title('Plan renewed')->success()->send();
                    });
    }

    /** Record that someone called the customer about renewing. */
    public static function markCalledAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('markCalled')->label('Mark called')->icon('heroicon-o-phone-arrow-up-right')->color('gray')
            ->visible(fn (PlanEnquiry $r) => $r->status === 'active' && ! $r->renewal_called_at)
            ->form([
                Forms\Components\Textarea::make('note')->label('What did they say? (optional)')->placeholder('e.g. Will confirm on Monday')->rows(2),
            ])
            ->action(function (PlanEnquiry $r, array $data) {
                $who = auth()->user()?->name ?? 'Admin';
                $r->update([
                    'renewal_called_at' => now(),
                    'renewal_called_by' => $who,
                    'admin_notes' => trim(($r->admin_notes ? $r->admin_notes . "\n" : '') . now()->format('d M') . " renewal call by {$who}" . (! empty($data['note']) ? ': ' . $data['note'] : '')),
                ]);
                \Filament\Notifications\Notification::make()->title('Marked as called')->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlanEnquiries::route('/'),
            'edit'  => Pages\EditPlanEnquiry::route('/{record}/edit'),
        ];
    }
}
