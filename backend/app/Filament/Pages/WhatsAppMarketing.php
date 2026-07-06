<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use App\Models\ReceiptSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class WhatsAppMarketing extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'WhatsApp Marketing';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.whats-app-marketing';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('📢 WhatsApp Broadcast Campaign')
                    ->description('Send custom automated WhatsApp marketing messages and images directly to your customer base in the background.')
                    ->schema([
                        Forms\Components\Radio::make('recipients_type')
                            ->label('Target Audience')
                            ->options([
                                'all' => 'All Customers',
                                'selected' => 'Select Specific Customers',
                            ])
                            ->default('all')
                            ->live(),
                        
                        Forms\Components\Select::make('customer_ids')
                            ->label('Select Recipients')
                            ->multiple()
                            ->searchable()
                            ->options(Customer::pluck('customer_name', 'id'))
                            ->visible(fn (Forms\Get $get) => $get('recipients_type') === 'selected')
                            ->required(fn (Forms\Get $get) => $get('recipients_type') === 'selected'),

                        Forms\Components\Textarea::make('message')
                            ->label('Campaign Message')
                            ->required()
                            ->rows(4)
                            ->placeholder('Type your marketing promotion or announcement here...'),

                        Forms\Components\FileUpload::make('image')
                            ->label('Attach Image (Optional)')
                            ->image()
                            ->directory('whatsapp-marketing')
                            ->disk('public')
                            ->maxSize(2048) // 2MB
                            ->helperText('PNG, JPG, or JPEG format. Image will be sent along with your message.'),
                    ])
            ])
            ->statePath('data');
    }

    public function sendBroadcast(): void
    {
        $formData = $this->form->getState();

        // 1. Get recipients list
        if ($formData['recipients_type'] === 'all') {
            $customers = Customer::all();
        } else {
            $customers = Customer::whereIn('id', $formData['customer_ids'])->get();
        }

        if ($customers->isEmpty()) {
            Notification::make()
                ->title('No Customers Selected')
                ->body('Please choose at least one customer to send the broadcast to.')
                ->danger()
                ->send();
            return;
        }

        $settings = ReceiptSetting::getSettings();
        $token = $settings->whatsapp_gateway_token;
        $payloadTemplate = $settings->whatsapp_gateway_payload_template ?: '{"to":"{phone}","body":"{message}"}';

        $hasImage = !empty($formData['image']);
        $imageUrl = null;

        if ($hasImage) {
            $imageUrl = asset('storage/' . $formData['image']);
        }

        // Determine gateway URL
        $gatewayUrl = ($hasImage && $settings->whatsapp_gateway_image_url) 
            ? $settings->whatsapp_gateway_image_url 
            : $settings->whatsapp_gateway_url;

        $sentCount = 0;
        $errorCount = 0;

        foreach ($customers as $customer) {
            $phone = preg_replace('/\D/', '', $customer->customer_phone);
            if (strlen($phone) === 10) {
                $phone = '91' . $phone;
            }

            if ($gatewayUrl) {
                $payload = str_replace(
                    ['{token}', '{phone}', '{message}', '{image}'],
                    [$token, $phone, $formData['message'], $imageUrl ?: ''],
                    $payloadTemplate
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
                        $params = [
                            'to' => $phone,
                            'token' => $token,
                        ];
                        if ($hasImage) {
                            $params['image'] = $imageUrl;
                            $params['caption'] = $formData['message'];
                        } else {
                            $params['body'] = $formData['message'];
                        }
                        
                        $client->post($gatewayUrl, [
                            'form_params' => $params,
                            'timeout' => 5
                        ]);
                    }
                    $sentCount++;
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("WhatsApp Page Broadcast Error for {$phone}: " . $e->getMessage());
                    $errorCount++;
                }
            } else {
                $mediaStr = $hasImage ? " [Image Attached: {$imageUrl}]" : "";
                \Illuminate\Support\Facades\Log::info("SIMULATED WhatsApp Broadcast sent to {$phone}: " . $formData['message'] . $mediaStr);
                $sentCount++;
            }
        }

        $this->form->fill([
            'recipients_type' => $formData['recipients_type'],
            'customer_ids' => $formData['customer_ids'] ?? [],
            'message' => $formData['message'],
        ]);

        if ($gatewayUrl) {
            if ($errorCount > 0) {
                Notification::make()
                    ->title("Broadcast Sent with Warnings")
                    ->body("Sent successfully to {$sentCount} customers. {$errorCount} failures logged.")
                    ->warning()
                    ->send();
            } else {
                Notification::make()
                    ->title("Broadcast Completed Successfully!")
                    ->body("WhatsApp marketing campaign has been broadcasted to {$sentCount} customers in the background!")
                    ->success()
                    ->send();
            }
        } else {
            Notification::make()
                ->title("Broadcast Simulation Completed")
                ->body("Simulated broadcast to {$sentCount} customers (No WhatsApp Gateway configured). Messages logged to laravel.log.")
                ->info()
                ->send();
        }
    }
}
