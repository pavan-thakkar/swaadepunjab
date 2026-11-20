<?php

namespace App\Services;

use App\Models\ReceiptSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Sends one WhatsApp text through the gateway set in Store & Receipt Config (same format as Customers → Broadcast). */
class WhatsAppGateway
{
    public static function configured(): bool
    {
        try {
            return filled(ReceiptSetting::first()?->whatsapp_gateway_url);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function send(?string $phone, string $message): bool
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (strlen($digits) < 10) {
            return false;
        }
        if (strlen($digits) === 10) {
            $digits = '91' . $digits;
        }
        $s = ReceiptSetting::first();
        if (! $s || ! filled($s->whatsapp_gateway_url)) {
            return false;
        }

        $template = $s->whatsapp_gateway_payload_template ?: '{"to":"{phone}","body":"{message}"}';
        // Escape for JSON before putting values into the template
        $esc = fn ($v) => substr(json_encode((string) $v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 1, -1);
        $payload = str_replace(['{token}', '{phone}', '{message}'], [$esc($s->whatsapp_gateway_token), $esc($digits), $esc($message)], $template);
        $decoded = json_decode($payload, true);

        try {
            $res = json_last_error() === JSON_ERROR_NONE
                ? Http::timeout(8)->acceptJson()->post($s->whatsapp_gateway_url, $decoded)
                : Http::timeout(8)->asForm()->post($s->whatsapp_gateway_url, ['to' => $digits, 'body' => $message, 'token' => $s->whatsapp_gateway_token]);
            if (! $res->successful()) {
                Log::warning('WhatsApp gateway error', ['status' => $res->status(), 'body' => mb_substr($res->body(), 0, 300)]);
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            Log::warning('WhatsApp gateway failed: ' . $e->getMessage());
            return false;
        }
    }
}
