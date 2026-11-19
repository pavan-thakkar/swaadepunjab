<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    /**
     * Return all available menu items, optionally filtered by category or search.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MenuItem::available();

        if ($request->filled('category') && $request->category !== 'all') {
            $query->byCategory($request->category);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        $items = $query->orderBy('is_featured', 'desc')->orderBy('rating', 'desc')->get();

        return response()->json([
            'data'       => $items,
            'categories' => MenuItem::available()->distinct()->pluck('category'),
        ]);
    }

    /**
     * "Goes well with" for a dish page, best first:
     * 1. dishes the owner picked for this dish
     * 2. dishes customers actually order together with it
     * 3. sides (breads, papad…) for a main dish, or popular mains for a side
     * 4. other bestsellers
     * Skips the dish itself, its variants ("Dal Makhani with Naan" vs "with Roti"),
     * sold-out dishes and anything passed in ?exclude=1,2 (already in the cart).
     */
    public function pairs(Request $request, MenuItem $menuItem): JsonResponse
    {
        $limit = 4;
        $exclude = collect(explode(',', (string) $request->query('exclude', '')))
            ->map(fn ($v) => (int) $v)->filter()->push($menuItem->id)->unique()->values()->all();
        $base = self::baseName($menuItem->name);
        $addonCats = \App\Models\Category::where('is_addon', true)->pluck('slug')->all();
        $isSide = in_array($menuItem->category, $addonCats, true);

        $menu = MenuItem::available()->whereNotIn('id', $exclude)->get()
            ->reject(fn ($m) => $base !== '' && self::baseName($m->name) === $base)
            ->keyBy('id');

        $picked = [];   // id => reason
        $take = function (iterable $ids, string $reason) use (&$picked, $menu, $limit) {
            foreach ($ids as $id) {
                if (count($picked) >= $limit) return;
                $id = (int) $id;
                if (isset($menu[$id]) && ! isset($picked[$id])) $picked[$id] = $reason;
            }
        };

        // 1. Owner's picks
        $take((array) ($menuItem->pairs_with ?? []), "Chef's pick");

        // 2. Ordered together (last 120 days, at least 2 orders)
        if (count($picked) < $limit) {
            $orderIds = \App\Models\OrderItem::where('menu_item_id', $menuItem->id)
                ->whereHas('order', fn ($q) => $q->whereNotIn('status', ['cancelled', 'awaiting_payment'])->where('created_at', '>=', now()->subDays(120)))
                ->latest('id')->limit(500)->pluck('order_id');
            if ($orderIds->isNotEmpty()) {
                $together = \App\Models\OrderItem::whereIn('order_id', $orderIds)
                    ->where('menu_item_id', '!=', $menuItem->id)
                    ->where(fn ($q) => $q->where('is_free', false)->orWhereNull('is_free'))
                    ->selectRaw('menu_item_id, COUNT(DISTINCT order_id) as c')
                    ->groupBy('menu_item_id')->havingRaw('COUNT(DISTINCT order_id) >= 2')
                    ->orderByDesc('c')->limit(12)->pluck('menu_item_id');
                $take($together, 'Often ordered together');
            }
        }

        // Real bestsellers first: featured, then most ordered (last 120 days), then rating; cheaper first on ties
        $pop = \App\Models\OrderItem::where('created_at', '>=', now()->subDays(120))
            ->selectRaw('menu_item_id, SUM(quantity) as q')->groupBy('menu_item_id')->pluck('q', 'menu_item_id');
        $rank = fn ($c) => $c->sort(fn ($a, $b) =>
            ((int) $b->is_featured <=> (int) $a->is_featured)
            ?: ((int) ($pop[$b->id] ?? 0) <=> (int) ($pop[$a->id] ?? 0))
            ?: ((float) $b->rating <=> (float) $a->rating)
            ?: ((float) $a->price <=> (float) $b->price))->values();

        // 3. Sides for a main dish (one from each sides category first, so it isn't 3 breads), mains for a side
        if (count($picked) < $limit) {
            if (! $isSide && $addonCats) {
                $byCat = $menu->filter(fn ($m) => in_array($m->category, $addonCats, true))->groupBy('category')->map($rank);
                for ($round = 0; $round < 3 && count($picked) < $limit; $round++) {
                    foreach ($byCat as $items) {
                        $m = $items->values()->get($round);
                        if ($m) $take([$m->id], 'Add a side');
                    }
                }
            } elseif ($isSide) {
                $take($rank($menu->reject(fn ($m) => in_array($m->category, $addonCats, true)))->pluck('id'), 'Pair it with');
            }
        }

        // 4. Bestsellers from other categories
        if (count($picked) < $limit) {
            $take($rank($menu->where('category', '!=', $menuItem->category))->pluck('id'), 'Popular');
        }

        $data = collect($picked)->map(function ($reason, $id) use ($menu) {
            $m = $menu[$id];
            $m->setAttribute('reason', $reason);
            return $m;
        })->values();

        return response()->json(['data' => $data]);
    }

    /** "Dal Makhani with 2 Butter Roti" → "dal makhani" (used to skip variants of the same dish). */
    private static function baseName(string $name): string
    {
        $n = mb_strtolower($name);
        $n = preg_split('/\s+(with|w\/|and|\+)\s+|[,(]/u', $n)[0] ?? $n;
        return trim(preg_replace('/\s+/', ' ', $n));
    }

    /** A dish by its web name: /menu/slug/paneer-tikka */
    public function showBySlug(string $slug): JsonResponse
    {
        $item = MenuItem::findBySlug($slug);
        if (! $item) {
            return response()->json(['message' => 'Dish not found'], 404);
        }
        return response()->json(['data' => $item]);
    }

    /**
     * Return a single menu item.
     */
    public function show(MenuItem $menuItem): JsonResponse
    {
        return response()->json(['data' => $menuItem]);
    }
}
