<?php

namespace App\Models;

use App\Support\TimeWindow;
use Illuminate\Database\Eloquent\Model;

/** A timed section on the website, e.g. "☕ Breakfast · 4 PM – 7 PM" with hand-picked dishes. */
class MenuSection extends Model
{
    protected $fillable = [
        'title', 'subtitle', 'emoji', 'available_from', 'available_until', 'menu_item_ids',
        'sell_only_in_time', 'outside_hours', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'menu_item_ids'     => 'array',
        'sell_only_in_time' => 'boolean',
        'is_active'         => 'boolean',
        'sort_order'        => 'integer',
    ];

    private static ?array $cache = null;

    /** Active sections (cached per request). */
    public static function activeList(): array
    {
        if (self::$cache === null) {
            try {
                self::$cache = static::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()->all();
            } catch (\Throwable $e) {
                self::$cache = [];
            }
        }
        return self::$cache;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    public function dishIds(): array
    {
        return array_values(array_map('intval', array_filter((array) ($this->menu_item_ids ?? []))));
    }

    public function label(): string
    {
        return TimeWindow::label($this->available_from, $this->available_until);
    }

    public function isOpen(): bool
    {
        return TimeWindow::isOpen($this->available_from, $this->available_until);
    }

    /** First active section that limits selling hours for this dish. */
    public static function limitingFor(int $menuItemId): ?self
    {
        foreach (static::activeList() as $s) {
            if ($s->sell_only_in_time && in_array($menuItemId, $s->dishIds(), true)) {
                return $s;
            }
        }
        return null;
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flush());
        static::deleted(fn () => static::flush());
    }
}
