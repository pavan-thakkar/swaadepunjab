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

    /** Why online payment is (not) showing on the website — for the admin settings page. */
    public static function statusText(): string
    {
        $s = self::settings();
        $hasSecretSaved = filled($s->getRawOriginal('razorpay_key_secret'));
        return match (true) {
            ! $s->razorpay_enabled      => '⚪ OFF — turn on "Accept online payments on the website" and Save.',
            ! filled($s->razorpay_key_id) => '🔴 Not showing — the Key ID is not saved.',
            ! $hasSecretSaved           => '🔴 Not showing — the Key Secret is not saved. Paste it and Save.',
            ! filled(self::secret())    => '🔴 Not showing — the saved Key Secret can\'t be read. Paste it again and Save.',
            default                     => '🟢 ON — customers see "UPI, Card or Netbanking" at checkout (' . (self::isTestMode() ? 'TEST mode' : 'LIVE mode') . ').',
        };
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
            Log::warning('Razorpay webhook rejected: ' . (! $secret ? 'no webhook secret saved in admin' : 'signature does not match — the Webhook Secret in admin must be the same as in Razorpay'));
            return false;
        }
        $data = json_decode($rawBody, true) ?: [];
        $event = $data['event'] ?? '';
        if ($event === 'payment_link.paid') {
            $linkId = $data['payload']['payment_link']['entity']['id'] ?? null;
            $o = $linkId ? Order::where('pay_link_id', $linkId)->first() : null;
            if ($o) self::markPaidAtDoor($o, $data['payload']['payment']['entity']['id'] ?? null);
            return true;
        }
        $payment = $data['payload']['payment']['entity'] ?? [];
        $rzpOrderId = $payment['order_id'] ?? ($data['payload']['order']['entity']['id'] ?? null);
        if (! $rzpOrderId) {
            return true; // not about one of our orders; acknowledge
        }
        $order = Order::where('razorpay_order_id', $rzpOrderId)->first();
        if (! $order) {
            return true;
        }
        if (in_array($event, ['payment.captured', 'payment.authorized', 'order.paid'], true)) {
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

    /**
     * Ask Razorpay directly whether the order was paid (customer came back, "check again", admin button).
     * "authorized" counts as paid: the bank approved it and Razorpay captures it automatically.
     * @return string 'paid' | 'pending' | 'failed' | 'error'
     */
    public static function syncFromRazorpayOrder(Order $order): string
    {
        if ($order->payment_status === 'paid') {
            return 'paid';
        }
        if (! $order->razorpay_order_id || ! filled(self::keyId()) || ! filled(self::secret())) {
            return 'error';
        }
        $payments = self::request('get', "/orders/{$order->razorpay_order_id}/payments");
        if ($payments === null) {
            Log::warning("Razorpay sync failed for {$order->order_number} ({$order->razorpay_order_id})");
            return 'error';
        }
        $sawFailed = false;
        foreach (($payments['items'] ?? []) as $p) {
            $st = $p['status'] ?? '';
            if (in_array($st, ['captured', 'authorized'], true)) {
                self::markPaid($order, $p['id'] ?? null);
                Log::info("Razorpay sync: {$order->order_number} paid ({$st})");
                return 'paid';
            }
            if ($st === 'failed') $sawFailed = true;
        }
        Log::info("Razorpay sync: {$order->order_number} not paid yet", ['count' => count($payments['items'] ?? [])]);
        return $sawFailed ? 'failed' : 'pending';
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

    // ---------------- Pay online at the door (COD order, customer wants to pay online) ----------------

    /**
     * Make (or reuse) a Razorpay payment link for the order's amount. Customer pays by UPI / card / netbanking.
     * @return string|null the link (null = Razorpay not set up / error)
     */
    public static function payLinkFor(Order $order): ?string
    {
        if ($order->pay_link_url && $order->pay_link_id) {
            return $order->pay_link_url;
        }
        if (! filled(self::keyId()) || ! filled(self::secret())) {
            return null; // keys not saved in Store & Receipt Config
        }
        $digits = substr(preg_replace('/\D/', '', (string) $order->customer_phone), -10);
        $body = [
            'amount' => (int) round(((float) $order->total) * 100),
            'currency' => 'INR',
            'accept_partial' => false,
            'description' => 'Swaad E Punjab order ' . $order->order_number,
            'reference_id' => mb_substr($order->order_number . '-' . now()->format('His'), 0, 40),
            'notify' => ['sms' => false, 'email' => false],
            'reminder_enable' => false,
            'expire_by' => now()->addDay()->timestamp,
            'notes' => ['order_number' => $order->order_number, 'order_id' => (string) $order->id],
        ];
        if (strlen($digits) === 10) {
            $body['customer'] = ['name' => mb_substr((string) $order->customer_name, 0, 50), 'contact' => '+91' . $digits];
        }
        $res = self::request('post', '/payment_links', $body);
        if (! $res || empty($res['short_url'])) {
            return null;
        }
        $order->forceFill(['pay_link_id' => $res['id'] ?? null, 'pay_link_url' => $res['short_url']])->saveQuietly();
        return $res['short_url'];
    }

    /** Ask Razorpay if the door payment link was paid. @return string 'paid' | 'pending' | 'error' */
    public static function syncPayLink(Order $order): string
    {
        if ($order->payment_status === 'paid') return 'paid';
        if (! $order->pay_link_id) return 'error';
        $res = self::request('get', '/payment_links/' . $order->pay_link_id);
        if (! $res) return 'error';
        if (($res['status'] ?? '') === 'paid') {
            $pid = $res['payments'][0]['payment_id'] ?? ($res['payments'][0]['id'] ?? null);
            self::markPaidAtDoor($order, $pid);
            return 'paid';
        }
        return 'pending';
    }

    /** Door payment done: mark paid and settle the bill as Online (credited to the delivery boy who took it). */
    public static function markPaidAtDoor(Order $order, ?string $paymentId): void
    {
        DB::transaction(function () use ($order, $paymentId) {
            $o = Order::lockForUpdate()->find($order->id);
            if (! $o || $o->payment_status === 'paid') return;
            $o->forceFill(['payment_status' => 'paid', 'razorpay_payment_id' => $paymentId, 'paid_at' => now()])->save();
            if ($o->needsSettlement()) {
                $o->settle('online', [], 'Paid online at the door (Razorpay link)', $o->rider ?: null);
            }
        });
        $order->refresh();
    }

    /** Small HTTP helper with the key + secret. Returns decoded JSON or null. */
    /** How much of this online payment can still be refunded. */
    public static function refundable(Order $order): float
    {
        if ($order->payment_method !== 'razorpay' || ! in_array($order->payment_status, ['paid', 'refunded'], true) || ! $order->razorpay_payment_id) {
            return 0.0;
        }
        return max(0, round((float) $order->total - (float) $order->refund_amount, 2));
    }

    /**
     * Refund money to the customer through Razorpay (goes back to their UPI / card / bank in 5–7 days).
     *
     * @return array{0: bool, 1: string} [ok, message]
     */
    public static function refund(Order $order, float $amount, ?string $note = null): array
    {
        $max = self::refundable($order);
        $amount = round($amount, 2);
        if ($max <= 0) {
            return [false, 'Nothing left to refund on this order.'];
        }
        if ($amount <= 0 || $amount > $max) {
            return [false, 'Refund amount must be between ₹1 and ₹' . number_format($max, 2) . '.'];
        }
        $key = self::keyId();
        $secret = self::secret();
        if (! $key || ! $secret) {
            return [false, 'Razorpay keys are missing — add them in Store & Receipt Config.'];
        }

        try {
            $res = Http::withBasicAuth($key, $secret)->acceptJson()->timeout(20)
                ->post(self::API . '/payments/' . $order->razorpay_payment_id . '/refund', array_filter([
                    'amount' => (int) round($amount * 100),
                    'notes'  => array_filter(['order' => $order->order_number, 'reason' => $note ? mb_substr($note, 0, 200) : null]),
                ]));
        } catch (\Throwable $e) {
            Log::warning('Razorpay refund failed: ' . $e->getMessage());
            return [false, 'Could not reach Razorpay. Please try again.'];
        }

        if (! $res->successful()) {
            $msg = $res->json('error.description') ?: 'Razorpay refused the refund.';
            Log::warning('Razorpay refund error', ['order' => $order->id, 'status' => $res->status(), 'body' => mb_substr($res->body(), 0, 500)]);
            return [false, $msg];
        }

        $refunded = round((float) $order->refund_amount + $amount, 2);
        $order->forceFill([
            'refund_amount'      => $refunded,
            'refunded_at'        => now(),
            'razorpay_refund_id' => (string) $res->json('id'),
            'refund_note'        => $note ? mb_substr($note, 0, 255) : $order->refund_note,
            'payment_status'     => $refunded >= (float) $order->total - 0.01 ? 'refunded' : 'paid',
        ])->save();

        return [true, '₹' . number_format($amount, 2) . ' refund started (' . $res->json('id') . '). It reaches the customer in 5–7 working days.'];
    }

    private static function request(string $method, string $path, array $body = [], ?string $key = null, ?string $secret = null): ?array
    {
        $secret = $secret ?: self::secret();
        $key = $key ?: self::keyId();
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

    /**
     * Admin "Test connection" button. Uses the keys typed in the form; an empty box falls back to the saved value.
     */
    public static function testConnection(?string $typedKey = null, ?string $typedSecret = null): array
    {
        $key = trim((string) ($typedKey ?: self::keyId()));
        $secret = trim((string) ($typedSecret ?: self::secret()));
        if ($key === '' || $secret === '') {
            return [false, 'Enter both the Key ID and Key Secret.'];
        }
        $res = self::request('get', '/orders?count=1', [], $key, $secret);
        $mode = str_starts_with($key, 'rzp_test_') ? 'TEST mode — no real money' : 'LIVE mode — real payments';
        return $res !== null
            ? [true, "Connected to Razorpay ({$mode}). Now click Save, and turn on Accept online payments."]
            : [false, 'Razorpay rejected these keys. Copy the Key ID and Key Secret again from the downloaded key file (no extra spaces), and make sure both are from the same mode (Live or Test).'];
    }
}
