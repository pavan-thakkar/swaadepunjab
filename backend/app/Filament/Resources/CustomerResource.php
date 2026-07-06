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
    protected static ?string $model = Customer::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Customers';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 1;

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
                    ->color('info')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_spent')
                    ->label('Total Spent')
                    ->formatStateUsing(fn ($state) => '₹' . number_format((float)$state, 2))
                    ->sortable()
                    ->color('success')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('last_order_at')
                    ->label('Last Order')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('city')
                    ->options([
                        'gadhinagar' => 'Gandhinagar',
                        'ahmedabad'  => 'Ahmedabad',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('View Profile')
                    ->modalHeading('Customer Profile Details')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                
                Tables\Actions\Action::make('whatsapp_single')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->form([
                        Forms\Components\Textarea::make('message')
                            ->label('Message Content')
                            ->required()
                            ->default(fn ($record) => "Hello {$record->customer_name}!\n\n")
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
                                ->default("Hello!\n\nWe have a special discount for you today at Swaad E Punjab! Use code PUNJAB10 to get 10% off on your next order.\n\nOrder now: http://localhost:3000")
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
