<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReceiptSettingResource\Pages;
use App\Models\ReceiptSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReceiptSettingResource extends Resource
{
    use \App\Filament\Concerns\HasRoleAccess;

    protected static function roleAccess(): array
    {
        return ['view' => ['owner'], 'edit' => ['owner'], 'delete' => ['owner']];
    }

    protected static ?string $model = ReceiptSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Store & Receipt Config';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('🧾 Thermal Receipt Template Config')
                    ->description('Set your outlet name, address, contact, GST numbers, and custom thank you messages.')
                    ->schema([
                        Forms\Components\TextInput::make('outlet_name')
                            ->label('Outlet Name')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g. Swad E Punjab'),
                        Forms\Components\TextInput::make('outlet_phone')
                            ->label('Outlet Phone/Contact')
                            ->required()
                            ->maxLength(50)
                            ->placeholder('e.g. +91 98765 43210'),
                        Forms\Components\TextInput::make('gst_number')
                            ->label('GSTIN (Optional)')
                            ->maxLength(50)
                            ->placeholder('e.g. 07AAAAA1111A1Z1 (leave blank if not applicable)'),
                        Forms\Components\TextInput::make('footer_message')
                            ->label('Footer Slogan / Message')
                            ->maxLength(200)
                            ->placeholder('e.g. Swad E Punjab Taste of Tradition'),
                        Forms\Components\Textarea::make('outlet_address')
                            ->label('Outlet Address')
                            ->required()
                            ->columnSpanFull()
                            ->rows(3)
                            ->placeholder('e.g. 123, Food Court, Corporate Tower, Amritsar'),
                    ])->columns(2),

                Forms\Components\Section::make('💬 Automated WhatsApp Marketing Gateway Config')
                    ->description('Integrate with any bulk WhatsApp API provider (e.g. UltraMsg, Wati, Twilio, or custom gateways) for 1-click background broadcasts.')
                    ->schema([
                        Forms\Components\TextInput::make('whatsapp_gateway_url')
                            ->label('WhatsApp Text API URL')
                            ->placeholder('e.g. https://api.ultramsg.com/instance123/messages/chat')
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('whatsapp_gateway_image_url')
                            ->label('WhatsApp Image API URL')
                            ->placeholder('e.g. https://api.ultramsg.com/instance123/messages/image')
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('whatsapp_gateway_token')
                            ->label('API Token / Auth Key')
                            ->placeholder('e.g. your_secret_token_here')
                            ->password()
                            ->revealable()
                            ->columnSpan(1),
                        Forms\Components\Textarea::make('whatsapp_gateway_payload_template')
                            ->label('JSON Payload Template')
                            ->placeholder('e.g. {"token": "{token}", "to": "{phone}", "body": "{message}", "image": "{image}"}')
                            ->helperText('Use placeholder tokens: {token} for the token, {phone} for recipient number, {message} for text content/caption, and {image} for the public image URL. Must be valid JSON.')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(3),

                Forms\Components\Section::make('🚗 Distance-Based Delivery Fee Config')
                    ->description('Automatically calculate delivery fees based on the actual road distance (in kilometers) between your outlet and the customer.')
                    ->schema([
                        Forms\Components\TextInput::make('google_maps_api_key')
                            ->label('Google Maps API Key')
                            ->placeholder('AIzaSy...')
                            ->password()
                            ->revealable()
                            ->helperText('Required for actual driving distance calculations using Google Distance Matrix API. If left empty, fallback fee will be used.')
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('delivery_fallback_fee')
                            ->label('Fallback Delivery Fee (₹)')
                            ->numeric()
                            ->required()
                            ->prefix('₹')
                            ->placeholder('50.00')
                            ->helperText('Used if Google API key is missing or distance cannot be computed.')
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('delivery_base_km')
                            ->label('Base Distance (KM)')
                            ->numeric()
                            ->required()
                            ->placeholder('3.00')
                            ->helperText('Distance range covered under base fee.')
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('delivery_base_fee')
                            ->label('Base Delivery Fee (₹)')
                            ->numeric()
                            ->required()
                            ->prefix('₹')
                            ->placeholder('30.00')
                            ->helperText('Fee charged for deliveries within the base distance.')
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('delivery_extra_fee_per_km')
                            ->label('Extra Fee Per KM (₹)')
                            ->numeric()
                            ->required()
                            ->prefix('₹')
                            ->placeholder('10.00')
                            ->helperText('Additional fee charged per kilometer beyond the base distance.')
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('delivery_max_distance')
                            ->label('Maximum Delivery Radius (KM)')
                            ->numeric()
                            ->required()
                            ->placeholder('20.00')
                            ->helperText('Maximum distance you deliver to. Orders beyond this will be rejected.')
                            ->columnSpan(1),
                    ])->columns(3),

                Forms\Components\Section::make('🛵 Delivery Availability')
                    ->description('Turn home delivery on/off, or allow it only during set hours. Dine In and Take Away always stay open.')
                    ->schema([
                        Forms\Components\Toggle::make('delivery_enabled')
                            ->label('Home Delivery is ON')
                            ->helperText('Switch off to stop all delivery orders right now.')
                            ->default(true)
                            ->live()
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('delivery_hours_enabled')
                            ->label('Only deliver during these hours')
                            ->default(false)
                            ->live()
                            ->visible(fn (Forms\Get $get) => (bool) $get('delivery_enabled'))
                            ->columnSpanFull(),
                        Forms\Components\TimePicker::make('delivery_start_time')
                            ->label('Delivery starts at')
                            ->seconds(false)
                            ->native(false)
                            ->displayFormat('h:i A')
                            ->visible(fn (Forms\Get $get) => $get('delivery_enabled') && $get('delivery_hours_enabled'))
                            ->required(fn (Forms\Get $get) => $get('delivery_enabled') && $get('delivery_hours_enabled')),
                        Forms\Components\TimePicker::make('delivery_end_time')
                            ->label('Delivery ends at')
                            ->helperText('Can be after midnight, e.g. 6:00 PM to 2:00 AM.')
                            ->seconds(false)
                            ->native(false)
                            ->displayFormat('h:i A')
                            ->visible(fn (Forms\Get $get) => $get('delivery_enabled') && $get('delivery_hours_enabled'))
                            ->required(fn (Forms\Get $get) => $get('delivery_enabled') && $get('delivery_hours_enabled')),
                        Forms\Components\TextInput::make('delivery_off_message')
                            ->label('Message to customers when delivery is not available (optional)')
                            ->placeholder('e.g. Delivery band hai, Take Away available hai 🙏')
                            ->maxLength(200)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('⭐ Loyalty Points')
                    ->description('Customers earn points when an order is delivered and use them as a discount on later orders.')
                    ->schema([
                        Forms\Components\Toggle::make('loyalty_enabled')
                            ->label('Loyalty Points are ON')
                            ->helperText('Switch off to stop earning and using points. Customers keep their balance for when you switch it on again.')
                            ->default(false)
                            ->live()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('loyalty_earn_per_100')
                            ->label('Points earned per ₹100 spent')
                            ->numeric()->minValue(0)->default(5)
                            ->helperText('e.g. 5 → a ₹600 order earns 30 points.')
                            ->visible(fn (Forms\Get $get) => (bool) $get('loyalty_enabled'))
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? $state : 5), // never save empty into a required column
                        Forms\Components\TextInput::make('loyalty_point_value')
                            ->label('Value of 1 point')
                            ->numeric()->minValue(0.01)->default(1)->prefix('₹')
                            ->helperText('e.g. ₹1 → 100 points = ₹100 off.')
                            ->visible(fn (Forms\Get $get) => (bool) $get('loyalty_enabled'))
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? $state : 1), // never save empty into a required column
                        Forms\Components\TextInput::make('loyalty_min_redeem')
                            ->label('Minimum points to use')
                            ->numeric()->integer()->minValue(0)->default(50)
                            ->visible(fn (Forms\Get $get) => (bool) $get('loyalty_enabled'))
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? $state : 50), // never save empty into a required column
                        Forms\Components\TextInput::make('loyalty_max_redeem_percent')
                            ->label('Max part of an order paid with points')
                            ->numeric()->integer()->minValue(1)->maxValue(100)->default(50)->suffix('%')
                            ->visible(fn (Forms\Get $get) => (bool) $get('loyalty_enabled'))
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? $state : 50), // never save empty into a required column
                    ])->columns(2),

                Forms\Components\Section::make('💳 Online Payments (Razorpay)')
                    ->description('Customers pay by UPI, card, wallet or netbanking. An order reaches the kitchen only after Razorpay confirms the payment.')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Placeholder::make('razorpay_help')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->content(fn () => new \Illuminate\Support\HtmlString(
                                '<ol style="margin:0;padding-left:18px;line-height:1.7;font-size:13px">'
                                . '<li>Razorpay Dashboard → <b>Account &amp; Settings → API Keys</b> → <b>Generate Key</b>. Copy the <b>Key ID</b> and <b>Key Secret</b> into the boxes below.</li>'
                                . '<li>Razorpay Dashboard → <b>Account &amp; Settings → Webhooks</b> → <b>Add New Webhook</b>.<br>Webhook URL: <code style="background:#FFF1CC;padding:2px 6px;border-radius:6px;user-select:all">' . e(url('/api/razorpay/webhook')) . '</code><br>Secret: make up a long password and paste the same one below. Active events: <b>payment.captured</b>, <b>payment.failed</b>, <b>order.paid</b>.</li>'
                                . '<li>Save, click <b>Test connection</b>, then turn on <b>Accept online payments</b>.</li>'
                                . '<li>Start with <b>Test</b> keys (rzp_test_…) — no real money. When your Razorpay account is activated, switch to <b>Live</b> keys (rzp_live_…) and set up the webhook again in Live mode.</li>'
                                . '</ol>'
                            )),
                        Forms\Components\Toggle::make('razorpay_enabled')
                            ->label('Accept online payments on the website')
                            ->helperText('Needs the Key ID and Key Secret below.')
                            ->inline(false)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('razorpay_key_id')
                            ->label('Key ID')
                            ->placeholder('rzp_test_xxxxxxxxxxxxxx or rzp_live_xxxxxxxxxxxxxx')
                            ->regex('/^rzp_(test|live)_[A-Za-z0-9]+$/')
                            ->validationMessages(['regex' => 'The Key ID starts with rzp_test_ or rzp_live_.'])
                            ->maxLength(64)
                            ->helperText(fn (?string $state) => str_starts_with((string) $state, 'rzp_live_') ? '🟢 LIVE mode — real payments' : (str_starts_with((string) $state, 'rzp_test_') ? '🧪 TEST mode — no real money' : null)),
                        Forms\Components\TextInput::make('razorpay_key_secret')
                            ->label('Key Secret')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->placeholder(fn ($record) => $record && filled($record->getRawOriginal('razorpay_key_secret')) ? '•••••••• saved — leave empty to keep' : 'Paste the Key Secret')
                            ->helperText('Stored encrypted. Never shown again after saving.')
                            ->maxLength(128)
                            ->dehydrated(fn ($state) => filled($state)),
                        Forms\Components\TextInput::make('razorpay_webhook_secret')
                            ->label('Webhook Secret')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->placeholder(fn ($record) => $record && filled($record->getRawOriginal('razorpay_webhook_secret')) ? '•••••••• saved — leave empty to keep' : 'Same secret you typed in Razorpay Webhooks')
                            ->helperText('Confirms payments even if the customer closes the page right after paying.')
                            ->maxLength(128)
                            ->dehydrated(fn ($state) => filled($state)),
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('testRazorpay')
                                ->label('Test connection')
                                ->icon('heroicon-o-signal')
                                ->color('gray')
                                ->action(function () {
                                    [$ok, $msg] = \App\Services\Razorpay::testConnection();
                                    \Filament\Notifications\Notification::make()
                                        ->title($ok ? 'Razorpay connected' : 'Razorpay not connected')
                                        ->body($msg . ($ok ? '' : ' (Save your changes first, then test.)'))
                                        ->{$ok ? 'success' : 'danger'}()
                                        ->send();
                                }),
                        ])->columnSpanFull(),
                    ])->columns(3),

                Forms\Components\Section::make('✏️ Customer Order Changes')
                    ->description('How long customers can add/remove items or change special instructions after placing an order.')
                    ->schema([
                        Forms\Components\TextInput::make('order_edit_minutes')
                            ->label('Time allowed to change order')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(60)
                            ->default(2)
                            ->required()
                            ->suffix('minutes')
                            ->helperText('Set 0 to turn off order changes. Changes also stop as soon as you accept the order.'),
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
                Tables\Columns\TextColumn::make('outlet_name')
                    ->label('Outlet Name')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('outlet_phone')
                    ->label('Contact'),
                Tables\Columns\TextColumn::make('gst_number')
                    ->label('GSTIN')
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('outlet_address')
                    ->label('Address')
                    ->limit(50),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageReceiptSettings::route('/'),
        ];
    }
}
