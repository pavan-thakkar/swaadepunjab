<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    use \App\Filament\Concerns\HasRoleAccess;

    protected static function roleAccess(): array
    {
        return ['view' => ['owner', 'manager'], 'edit' => ['owner', 'manager'], 'delete' => ['owner']];
    }

    protected static ?string $model = Customer::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Customers';
    protected static ?string $navigationGroup = 'Insights';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('👤 Customer Profile')->schema([
                Forms\Components\TextInput::make('customer_name')
                    ->label('Full Name')
                    ->disabled(),
                Forms\Components\TextInput::make('customer_email')
                    ->label('Email Address')
                    ->disabled(),
                Forms\Components\TextInput::make('customer_phone')
                    ->label('Phone Number')
                    ->disabled(),
                Forms\Components\TextInput::make('city')
                    ->label('City')
                    ->disabled(),
                Forms\Components\Textarea::make('delivery_address')
                    ->label('Last Known Address')
                    ->disabled()
                    ->columnSpanFull(),
            ])->columns(3),

            Forms\Components\Section::make('📊 Order Summary')->schema([
                Forms\Components\TextInput::make('total_orders')
                    ->label('Total Orders Placed')
                    ->disabled(),
                Forms\Components\TextInput::make('total_spent')
                    ->label('Total Revenue Generated')
                    ->prefix('₹')
                    ->disabled(),
                Forms\Components\DateTimePicker::make('last_order_at')
                    ->label('Last Active Date')
                    ->disabled(),
            ])->columns(3),
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
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('customer_phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('customer_email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('city')
                    ->label('City')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_orders')
                    ->label('Orders')
                    ->badge()
                    ->color(fn ($state) => (int) $state >= 5 ? 'success' : ((int) $state <= 1 ? 'gray' : 'info'))
                    ->description(fn ($record) => (int) $record->total_orders >= 5 ? 'Regular' : ((int) $record->total_orders <= 1 ? 'New' : null))
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_spent')
                    ->label('Total Spent')
                    ->formatStateUsing(fn ($state) => '₹' . number_format((float) $state, 2))
                    ->description(fn ($record) => (int) $record->total_orders > 0 ? 'avg ₹' . number_format((float) $record->total_spent / (int) $record->total_orders, 0) . ' / order' : null)
                    ->sortable()
                    ->color('success')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('last_order_at')
                    ->label('Last Order')
                    ->dateTime('d M Y')
                    ->description(fn ($record) => $record->last_order_at ? $record->last_order_at->diffForHumans() : null)
                    ->color(fn ($record) => $record->last_order_at && $record->last_order_at->lt(now()->subDays(30)) ? 'danger' : null)
                    ->sortable(),
                Tables\Columns\TextColumn::make('first_order_at')
                    ->label('Customer since')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('inactive')
                    ->label('Not ordered for')
                    ->placeholder('Any time')
                    ->options(['15' => '15+ days', '30' => '30+ days', '60' => '60+ days', '90' => '90+ days'])
                    // HAVING must go on the main query (->query() runs inside a WHERE group)
                    ->query(fn ($query) => $query)
                    ->baseQuery(fn ($query, array $data) => filled($data['value'] ?? null)
                        ? $query->havingRaw('MAX(created_at) < ?', [now()->subDays((int) $data['value'])])
                        : $query),
                Tables\Filters\SelectFilter::make('min_orders')
                    ->label('Orders')
                    ->placeholder('Any')
                    ->options(['1' => 'Only 1 (new)', '2' => '2 or more', '5' => '5 or more (regulars)', '10' => '10 or more'])
                    ->query(fn ($query) => $query)
                    ->baseQuery(function ($query, array $data) {
                        $v = $data['value'] ?? null;
                        if (! filled($v)) return $query;
                        $expr = "SUM(CASE WHEN status NOT IN ('cancelled','awaiting_payment') THEN 1 ELSE 0 END)";
                        return $v === '1' ? $query->havingRaw("{$expr} = 1") : $query->havingRaw("{$expr} >= ?", [(int) $v]);
                    }),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
            ->actions([
                Tables\Actions\Action::make('profile')
                    ->label('Profile')
                    ->icon('heroicon-o-user-circle')
                    ->color('gray')
                    ->modalHeading(fn ($record) => $record->customer_name . ' · ' . $record->customer_phone)
                    ->modalContent(fn ($record) => view('filament.customer-profile', ['c' => $record, 's' => Customer::summary((string) $record->customer_phone)]))
                    ->modalWidth('3xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Tables\Actions\Action::make('call')
                    ->label('Call')
                    ->icon('heroicon-o-phone')
                    ->color('info')
                    ->url(fn ($record) => 'tel:+91' . substr(preg_replace('/\D/', '', (string) $record->customer_phone), -10)),
                Tables\Actions\Action::make('whatsapp_single')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->form([
                        Forms\Components\Textarea::make('message')
                            ->label('Message Content')
                            ->required()
                            ->default(fn ($record) => "Hello {$record->customer_name}! 🙏\n\nWe miss you at Swaad E Punjab. Order again: https://swaadepunjab.com")
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        $phone = preg_replace('/\D/', '', $record->customer_phone);
                        if (strlen($phone) === 10) {
                            $phone = '91' . $phone;
                        }
                        $text = urlencode($data['message']);
                        $url = "https://api.whatsapp.com/send?phone={$phone}&text={$text}";
                        return redirect()->away($url);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('whatsapp_broadcast')
                        ->label('💬 WhatsApp Broadcast')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->color('success')
                        ->form([
                            Forms\Components\Textarea::make('message')
                                ->label('Marketing Message')
                                ->default("Hello!\n\nWe have a special discount for you today at Swaad E Punjab! Use code PUNJAB10 to get 10% off on your next order.\n\nOrder now: https://swaadepunjab.com")
                                ->required()
                                ->rows(4),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            $settings = \App\Models\ReceiptSetting::getSettings();
                            
                            $gatewayUrl = $settings->whatsapp_gateway_url;
                            $gatewayToken = $settings->whatsapp_gateway_token;
                            $payloadTemplate = $settings->whatsapp_gateway_payload_template;

                            $sentCount = 0;
                            $errorCount = 0;

                            foreach ($records as $record) {
                                $phone = preg_replace('/\D/', '', $record->customer_phone);
                                if (strlen($phone) === 10) {
                                    $phone = '91' . $phone;
                                }

                                if ($gatewayUrl) {
                                    $payload = $payloadTemplate ?: '{"to":"{phone}","body":"{message}"}';
                                    $payload = str_replace(
                                        ['{token}', '{phone}', '{message}'],
                                        [$gatewayToken, $phone, $data['message']],
                                        $payload
                                    );

                                    $decoded = json_decode($payload, true);
                                    
                                    try {
                                        $client = new \GuzzleHttp\Client();
                                        
                                        if (json_last_error() === JSON_ERROR_NONE) {
                                            $client->post($gatewayUrl, [
                                                'json' => $decoded,
                                                'headers' => [
                                                    'Accept' => 'application/json',
                                                    'Content-Type' => 'application/json',
                                                ],
                                                'timeout' => 5
                                            ]);
                                        } else {
                                            $client->post($gatewayUrl, [
                                                'form_params' => [
                                                    'to' => $phone,
                                                    'body' => $data['message'],
                                                    'token' => $gatewayToken
                                                ],
                                                'timeout' => 5
                                            ]);
                                        }
                                        $sentCount++;
                                    } catch (\Exception $e) {
                                        \Illuminate\Support\Facades\Log::error("WhatsApp Gateway Error for {$phone}: " . $e->getMessage());
                                        $errorCount++;
                                    }
                                } else {
                                    \Illuminate\Support\Facades\Log::info("SIMULATED WhatsApp Broadcast sent to {$phone}: " . $data['message']);
                                    $sentCount++;
                                }
                            }

                            if ($gatewayUrl) {
                                if ($errorCount > 0) {
                                    \Filament\Notifications\Notification::make()
                                        ->title("Broadcast Completed with Warnings")
                                        ->body("Sent successfully to {$sentCount} customers. {$errorCount} failures logged.")
                                        ->warning()
                                        ->send();
                                } else {
                                    \Filament\Notifications\Notification::make()
                                        ->title("Broadcast Sent successfully!")
                                        ->body("Automated WhatsApp broadcast sent to all {$sentCount} selected customers in the background!")
                                        ->success()
                                        ->send();
                                }
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->title("Broadcast Completed (Simulation)")
                                    ->body("Simulated WhatsApp broadcast sent to {$sentCount} customers (No gateway configured in Settings). Check Laravel logs!")
                                    ->info()
                                    ->send();
                            }
                        }),
                ])
            ])
            ->defaultSort('total_spent', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
        ];
    }
}
