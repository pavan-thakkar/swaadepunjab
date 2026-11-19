<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/**
 * Signed + encrypted login token for website customers (issued after OTP / Google / Firebase login).
 * Stateless: nothing stored in the database. Valid for 90 days.
 */
class CustomerToken
{
    public static function last10(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $phone);
        return strlen($digits) >= 10 ? substr($digits, -10) : null;
    }

    public static function issue(?string $phone, ?string $email): string
    {
        return Crypt::encryptString(json_encode([
            'p'   => self::last10($phone),
            'e'   => $email ? strtolower(trim($email)) : null,
            'exp' => now()->addDays(90)->timestamp,
        ]));
    }

    /** @return array{phone: ?string, email: ?string}|null */
    public static function fromRequest(Request $request): ?array
    {
        $raw = $request->bearerToken() ?: $request->header('X-Customer-Token');
        if (! $raw) {
            return null;
        }
        try {
            $data = json_decode(Crypt::decryptString($raw), true);
        } catch (\Throwable $e) {
            return null;
        }
        if (! is_array($data) || ($data['exp'] ?? 0) < now()->timestamp || (empty($data['p']) && empty($data['e']))) {
            return null;
        }
        return ['phone' => $data['p'] ?? null, 'email' => $data['e'] ?? null];
    }

    /** Phone number used for this customer's loyalty points / identity. */
    public static function phoneFor(?array $identity): ?string
    {
        if (! $identity) {
            return null;
        }
        if ($identity['phone']) {
            return $identity['phone'];
        }
        if ($identity['email']) {
            $latest = Order::where('customer_email', $identity['email'])->latest()->value('customer_phone');
            return self::last10($latest);
        }
        return null;
    }

    /** Does this logged-in customer own the given phone number? */
    public static function ownsPhone(?array $identity, ?string $phone): bool
    {
        $p = self::last10($phone);
        if (! $identity || ! $p) {
            return false;
        }
        if ($identity['phone'] && $identity['phone'] === $p) {
            return true;
        }
        if ($identity['email']) {
            return Order::where('customer_email', $identity['email'])
                ->where('customer_phone', 'like', '%' . $p)
                ->exists();
        }
        return false;
    }

    public static function ownsOrder(?array $identity, Order $order): bool
    {
        if (! $identity) {
            return false;
        }
        if ($identity['phone'] && self::last10($order->customer_phone) === $identity['phone']) {
            return true;
        }
        return $identity['email'] && $order->customer_email && strtolower($order->customer_email) === $identity['email'];
    }
}
