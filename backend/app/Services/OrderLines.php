<?php

namespace App\Services;

use App\Models\MenuItem;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Turns [{menu_item_id, quantity, options?}] into priced order_items rows.
 * Prices always come from the database, never from the browser.
 */
class OrderLines
{
    /** Same dish with the same choices = same line. */
    public static function key(int $menuItemId, ?array $options): string
    {
        $parts = array_map(fn ($o) => ($o['group'] ?? '') . '=' . ($o['choice'] ?? ''), $options ?? []);
        sort($parts);
        return $menuItemId . '|' . implode(';', $parts);
    }

    /**
     * @param  array  $items  request items (free lines are skipped)
     * @param  array  $keepKeys  line keys already in the order (allowed even if the dish/choice is now sold out)
     * @return array{0: array, 1: float}  [rows, subtotal]
     */
    public static function build(array $items, array $keepKeys = [], int $maxQty = 20): array
    {
        $merged = [];
        foreach ($items as $i) {
            if (! empty($i['is_free'])) continue;
            $id = (int) ($i['menu_item_id'] ?? 0);
            $opts = is_array($i['options'] ?? null) ? array_values($i['options']) : [];
            $k = self::key($id, $opts);
            if (! isset($merged[$k])) $merged[$k] = ['id' => $id, 'options' => $opts, 'qty' => 0];
            $merged[$k]['qty'] += (int) ($i['quantity'] ?? 0);
        }

        $dishes = MenuItem::whereIn('id', array_unique(array_column($merged, 'id')))->get()->keyBy('id');
        $rows = [];
        $subtotal = 0.0;

        foreach ($merged as $k => $m) {
            $dish = $dishes[$m['id']] ?? null;
            if (! $dish) self::fail('One of the dishes in your cart is no longer on the menu.');
            $kept = in_array($k, $keepKeys, true);
            if (! $dish->is_available && ! $kept) self::fail("{$dish->name} is currently unavailable.");

            try {
                $r = $dish->resolveOptions($m['options'], $kept);
            } catch (\InvalidArgumentException $e) {
                self::fail($e->getMessage());
            }

            $qty = max(1, min($maxQty, $m['qty']));
            $unit = round((float) $dish->price + $r['extra'], 2);
            $line = $unit * $qty;
            $subtotal += $line;
            $rows[] = [
                'menu_item_id' => $dish->id,
                'name'         => $r['label'] !== '' ? "{$dish->name} ({$r['label']})" : $dish->name,
                'options'      => $r['options'] ?: null,
                'price'        => $unit,
                'quantity'     => $qty,
                'subtotal'     => $line,
            ];
        }

        return [$rows, $subtotal];
    }

    /** Keys of the paid (non-free) lines already in an order. */
    public static function keysOf($orderItems): array
    {
        return collect($orderItems)->where('is_free', false)
            ->map(fn ($i) => self::key((int) $i->menu_item_id, $i->options ?? []))->values()->all();
    }

    private static function fail(string $message): never
    {
        throw new HttpResponseException(response()->json(['message' => $message], 422));
    }
}
