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
                    ->required(),
                Forms\Components\Textarea::make('admin_notes')
                    ->label('Your notes (only admins see this)')
                    ->placeholder('e.g. Called on 20 Nov, 25 people, starting 1 Dec at ₹3,200 each')
                    ->rows(3),
            ])->columns(2),

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
        $wa = fn (PlanEnquiry $r) => 'https://wa.me/91' . substr(preg_replace('/\D/', '', $r->phone), -10)
            . '?text=' . rawurlencode('Namaste ' . $r->name . ', thank you for your interest in our ' . ($r->plan_name ?: 'monthly') . ' plan at Swaad E Punjab.');

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
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state) => PlanEnquiry::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'new' => 'warning', 'contacted' => 'info', 'active' => 'success', default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(PlanEnquiry::STATUSES),
                Tables\Filters\SelectFilter::make('monthly_plan_id')->label('Plan')->relationship('plan', 'name'),
            ])
            ->actions([
                Tables\Actions\Action::make('call')->label('Call')->icon('heroicon-o-phone')->color('gray')
                    ->url(fn (PlanEnquiry $r) => 'tel:' . $r->phone),
                Tables\Actions\Action::make('whatsapp')->label('WhatsApp')->icon('heroicon-o-chat-bubble-left-right')->color('success')
                    ->url($wa, shouldOpenInNewTab: true),
                Tables\Actions\Action::make('contacted')->label('Mark contacted')->icon('heroicon-o-check')
                    ->visible(fn (PlanEnquiry $r) => $r->status === 'new')
                    ->action(fn (PlanEnquiry $r) => $r->update(['status' => 'contacted'])),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlanEnquiries::route('/'),
            'edit'  => Pages\EditPlanEnquiry::route('/{record}/edit'),
        ];
    }
}
