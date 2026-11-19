<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ReceiptSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Razorpay online payments.
 * Flow: create a Razorpay order on the server → customer pays in Razorpay Checkout →
 * server checks Razorpay's signature (or the webhook) → order is marked paid and goes to the kitchen.
 * The browser can never mark an order as paid by itself.
 */
class Razorpay
{
    private const API = 'https://api.razorpay.com/v1';

    public static function settings(): ReceiptSetting
    {
        return ReceiptSetting::getSettings();
    }

    /** Switched on in admin and both keys filled in. */
    public static function enabled(): bool
    {
        $s = self::settings();
        return (bool) $s->razorpay_enabled && filled($s->razorpay_key_id) && filled(self::secret());
    }

    public static function keyId(): ?string
    {
        return self::settings()->razorpay_key_id ?: null;
    }

    public static function isTestMode(): bool
    {
        return str_starts_with((string) self::keyId(), 'rzp_test_');
    }

    private static function secret(): ?string
    {
        try {
            return self::settings()->razorpay_key_secret ?: null;
        } catch (\Throwable $e) {
            return null; // e.g. APP_KEY changed and the secret can't be decrypted
        }
    }

    private static function webhookSecret(): ?string
    {
        try {
            return self::settings()->razorpay_webhook_secret ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Create (or reuse) the Razorpay order for this order.
     * @return array data for Razorpay Checkout on the website
     * @throws \RuntimeException with a customer-friendly message
     */
    public static function checkoutFor(Order $order): array
    {
        if (! self::enabled()) {
            throw new \RuntimeException('Online payment is not available right now. Please choose cash.');
        }
        $amount = (int) round(((float) $order->total) * 100); // paise
        if ($amount < 100) {
            throw new \RuntimeException('Online payment needs an order of at least ₹1.');
        }

        // Reuse the Razorpay order if the amount hasn't changed (e.g. the customer closed the popup and retries)
        $rzpOrderId = $order->razorpay_order_id;
        if ($rzpOrderId) {
            $existing = self::request('get', "/orders/{$rzpOrderId}");
            if (! $existing || (int) ($existing['amount'] ?? 0) !== $amount || ($existing['status'] ?? '') === 'paid') {
                if (($existing['status'] ?? '') === 'paid') {
                    self::syncFromRazorpayOrder($order);
                    throw new \RuntimeException('This order is already paid.');
                }
                $rzpOrderId = null;
            }
        }

        if (! $rzpOrderId) {
            $created = self::request('post', '/orders', [
                'amount'   => $amount,
                'currency' => 'INR',
                'receipt'  => substr($order->order_number, 0, 40),
                'notes'    => ['order_number' => $order->order_number, 'phone' => (string) $order->customer_phone],
            ]);
            if (! $created || empty($created['id'])) {
                throw new \RuntimeException('Could not start online payment. Please try again or choose cash.');
            }
            $rzpOrderId = $created['id'];
            $order->forceFill(['razorpay_order_id' => $rzpOrderId, 'payment_status' => 'pending'])->save();
        }

        $s = self::settings();
        return [
            'key'         => self::keyId(),
            'order_id'    => $rzpOrderId,
            'amount'      => $amount,
            'currency'    => 'INR',
            'name'        => $s->outlet_name ?: 'Swaad E Punjab',
            'description' => 'Order ' . $order->order_number,
            'prefill'     => [
                'name'    => (string) $order->customer_name,
                'email'   => (string) ($order->customer_email ?? ''),
                'contact' => (string) $order->customer_phone,
            ],
            'test_mode'   => self::isTestMode(),
        ];
    }

    /** Checkout handler result: check Razorpay's signature, then mark paid. */
    public static function verifyCheckout(Order $order, string $rzpOrderId, string $paymentId, string $signature): bool
    {
        $secret = self::secret();
        if (! $secret || ! $order->razorpay_order_id || ! hash_equals($order->razorpay_order_id, $rzpOrderId)) {
            return false;
        }
        $expected = hash_hmac('sha256', $rzpOrderId . '|' . $paymentId, $secret);
        if (! hash_equals($expected, $signature)) {
            return false;
        }
        self::markPaid($order, $paymentId);
        return true;
    }

    /** Razorpay webhook (payment.captured / order.paid): check signature on the raw body. */
    public static function handleWebhook(string $rawBody, ?string $signature): bool
    {
        $secret = self::webhookSecret();
        if (! $secret || ! $signature || ! hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature)) {
            return false;
        }
        $data = json_decode($rawBody, true) ?: [];
        $event = $data['event'] ?? '';
        $payment = $data['payload']['payment']['entity'] ?? [];
        $rzpOrderId = $payment['order_id'] ?? ($data['payload']['order']['entity']['id'] ?? null);
        if (! $rzpOrderId) {
            return true; // not about one of our orders; acknowledge
        }
        $order = Order::where('razorpay_order_id', $rzpOrderId)->first();
        if (! $order) {
            return true;
        }
        if (in_array($event, ['payment.captured', 'order.paid'], true)) {
            $paidAmount = (int) ($payment['amount'] ?? ($data['payload']['order']['entity']['amount_paid'] ?? 0));
            if ($paidAmount && $paidAmount < (int) round(((float) $order->total) * 100)) {
                Log::warning("Razorpay paid less than order total for {$order->order_number}");
                return true;
            }
            self::markPaid($order, $payment['id'] ?? $order->razorpay_payment_id);
        } elseif ($event === 'payment.failed' && $order->payment_status !== 'paid') {
            $order->forceFill(['payment_status' => 'failed'])->save();
        }
        return true;
    }

    /** Ask Razorpay directly whether the order was paid (used when the customer comes back). */
    public static function syncFromRazorpayOrder(Order $order): void
    {
        if (! $order->razorpay_order_id || $order->payment_status === 'paid' || ! self::enabled()) {
            return;
        }
        $payments = self::request('get', "/orders/{$order->razorpay_order_id}/payments");
        foreach (($payments['items'] ?? []) as $p) {
            if (($p['status'] ?? '') === 'captured') {
                self::markPaid($order, $p['id']);
                return;
            }
        }
    }

    /** Mark paid once, and send the order to the kitchen. Safe to call twice. */
    public static function markPaid(Order $order, ?string $paymentId): void
    {
        DB::transaction(function () use ($order, $paymentId) {
            $o = Order::lockForUpdate()->find($order->id);
            if (! $o || $o->payment_status === 'paid') {
                return;
            }
            $o->payment_status = 'paid';
            $o->razorpay_payment_id = $paymentId;
            $o->paid_at = now();
            if ($o->status === 'awaiting_payment') {
                $o->status = 'pending';                 // now the kitchen sees it
                $o->created_at = now();                 // order time = payment time (edit window, alerts)
                $o->estimated_delivery_at = now()->addMinutes(45);
            } elseif ($o->status === 'cancelled') {
                // Paid after it timed out: keep the money visible to the owner
                $o->status = 'pending';
                $o->created_at = now();
                $o->estimated_delivery_at = now()->addMinutes(45);
            }
            $o->save();
        });
        $order->refresh();
    }

    /** Small HTTP helper with the key + secret. Returns decoded JSON or null. */
    private static function request(string $method, string $path, array $body = []): ?array
    {
        $secret = self::secret();
        $key = self::keyId();
        if (! $secret || ! $key) {
            return null;
        }
        try {
            $http = Http::withBasicAuth($key, $secret)->acceptJson()->timeout(15);
            $res = $method === 'post' ? $http->post(self::API . $path, $body) : $http->get(self::API . $path);
            if (! $res->successful()) {
                Log::warning('Razorpay API error', ['path' => $path, 'status' => $res->status(), 'body' => mb_substr($res->body(), 0, 500)]);
                return null;
            }
            return $res->json();
        } catch (\Throwable $e) {
            Log::warning('Razorpay API failed: ' . $e->getMessage());
            return null;
        }
    }

    /** Admin "Test connection" button. */
    public static function testConnection(): array
    {
        if (! filled(self::keyId()) || ! filled(self::secret())) {
            return [false, 'Enter both the Key ID and Key Secret first.'];
        }
        $res = self::request('get', '/orders?count=1');
        return $res !== null
            ? [true, 'Connected to Razorpay (' . (self::isTestMode() ? 'TEST mode — no real money' : 'LIVE mode — real payments') . ').']
            : [false, 'Razorpay rejected these keys. Check the Key ID and Key Secret (and that both are from the same mode, Test or Live).'];
    }
}
