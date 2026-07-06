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
    protected static ?string $model = ReceiptSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Store & Receipt Config';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 4;

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
                    ])->columns(3)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
