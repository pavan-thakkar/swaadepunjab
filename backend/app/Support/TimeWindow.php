<?php

namespace App\Support;

use Carbon\Carbon;

/** Selling hours like 16:00–19:00 (overnight windows such as 22:00–02:00 work too). India time. */
class TimeWindow
{
    public static function minutes(?string $hm): ?int
    {
        if (! $hm || ! preg_match('/^(\d{1,2}):(\d{2})/', $hm, $m)) {
            return null;
        }
        return ((int) $m[1] % 24) * 60 + (int) $m[2];
    }

    public static function isOpen(?string $from, ?string $until, ?Carbon $now = null): bool
    {
        $f = self::minutes($from);
        $u = self::minutes($until);
        if ($f === null || $u === null || $f === $u) {
            return true; // incomplete window = no limit
        }
        $now = ($now ?? now())->copy()->setTimezone('Asia/Kolkata');
        $n = $now->hour * 60 + $now->minute;
        return $f < $u ? ($n >= $f && $n < $u) : ($n >= $f || $n < $u);
    }

    public static function format(?string $hm): string
    {
        $m = self::minutes($hm);
        if ($m === null) return '';
        $h = intdiv($m, 60);
        $min = $m % 60;
        $ampm = $h >= 12 ? 'PM' : 'AM';
        $h12 = $h % 12 ?: 12;
        return $min ? sprintf('%d:%02d %s', $h12, $min, $ampm) : "{$h12} {$ampm}";
    }

    public static function label(?string $from, ?string $until): string
    {
        return self::format($from) . ' – ' . self::format($until);
    }
}
