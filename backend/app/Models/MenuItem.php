<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    protected $fillable = [
        'name',
        'description',
        'category',
        'price',
        'quantity_label',
        'option_groups',
        'pairs_with',
        'image',
        'prep_time',
        'rating',
        'is_available',
        'is_featured',
    ];

    // Pretty web address: /item/paneer-tikka instead of /item/548
    protected $appends = ['slug'];

    public function getSlugAttribute(): string
    {
        return \Illuminate\Support\Str::slug((string) $this->name) ?: (string) $this->id;
    }

    /** Find a dish by its web name (available dishes first, then the oldest). */
    public static function findBySlug(string $slug): ?self
    {
        $slug = \Illuminate\Support\Str::slug($slug);
        if ($slug === '') return null;
        return static::query()->orderByDesc('is_available')->orderBy('id')->get()
            ->first(fn (self $m) => \Illuminate\Support\Str::slug((string) $m->name) === $slug);
    }

    protected $casts = [
        'price'        => 'decimal:2',
        'rating'       => 'decimal:1',
        'is_available' => 'boolean',
        'is_featured'  => 'boolean',
        'image'        => 'array',
        'option_groups' => 'array',
        'pairs_with'    => 'array',
    ];

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Clean list of option groups set by the admin.
     * Each: ['name', 'type' => single|multiple, 'required' => bool, 'max' => ?int, 'choices' => [['name','price','available']]]
     */
    public function groups(): array
    {
        $out = [];
        foreach (array_values((array) ($this->option_groups ?? [])) as $g) {
            $name = trim((string) ($g['name'] ?? ''));
            $choices = [];
            foreach (array_values((array) ($g['choices'] ?? [])) as $c) {
                $cn = trim((string) ($c['name'] ?? ''));
                if ($cn === '') continue;
                $choices[] = [
                    'name'      => $cn,
                    'price'     => round(max(0, (float) ($c['price'] ?? 0)), 2),
                    'available' => (bool) ($c['available'] ?? true),
                ];
            }
            if ($name === '' || ! $choices) continue;
            $type = ($g['type'] ?? 'single') === 'multiple' ? 'multiple' : 'single';
            $max = $type === 'single' ? 1 : (isset($g['max']) && (int) $g['max'] > 0 ? (int) $g['max'] : null);
            $out[] = ['name' => $name, 'type' => $type, 'required' => (bool) ($g['required'] ?? false), 'max' => $max, 'choices' => $choices];
        }
        return $out;
    }

    public function hasOptions(): bool
    {
        return count($this->groups()) > 0;
    }

    /**
     * Check the customer's picks against the admin's groups and price them.
     * $selected: [['group' => 'Choose bread', 'choice' => 'Butter Naan'], ...]
     * Returns ['extra' => float, 'options' => [[group, choice, price]], 'label' => 'Butter Naan, Extra butter'].
     * Throws \InvalidArgumentException with a customer-friendly message.
     */
    public function resolveOptions(array $selected, bool $allowUnavailable = false): array
    {
        $groups = $this->groups();
        $picked = [];
        foreach ($selected as $s) {
            $g = trim((string) ($s['group'] ?? ''));
            $c = trim((string) ($s['choice'] ?? ''));
            if ($g === '' || $c === '') continue;
            $picked[$g][$c] = true;
        }

        $known = array_column($groups, 'name');
        foreach (array_keys($picked) as $g) {
            if (! in_array($g, $known, true)) {
                throw new \InvalidArgumentException("The options for {$this->name} have changed. Please add it to the cart again.");
            }
        }

        $extra = 0.0;
        $options = [];
        foreach ($groups as $group) {
            $chosen = array_keys($picked[$group['name']] ?? []);
            if ($group['required'] && ! $chosen) {
                throw new \InvalidArgumentException("Please pick an option in \"{$group['name']}\" for {$this->name}.");
            }
            if ($group['max'] !== null && count($chosen) > $group['max']) {
                throw new \InvalidArgumentException("You can pick up to {$group['max']} for {$group['name']} ({$this->name}).");
            }
            // keep the admin's order of choices
            foreach ($group['choices'] as $choice) {
                if (! in_array($choice['name'], $chosen, true)) continue;
                if (! $choice['available'] && ! $allowUnavailable) {
                    throw new \InvalidArgumentException("{$choice['name']} is not available right now for {$this->name}.");
                }
                $extra += $choice['price'];
                $options[] = ['group' => $group['name'], 'choice' => $choice['name'], 'price' => $choice['price']];
            }
            $names = array_column($group['choices'], 'name');
            foreach ($chosen as $c) {
                if (! in_array($c, $names, true)) {
                    throw new \InvalidArgumentException("The options for {$this->name} have changed. Please add it to the cart again.");
                }
            }
        }

        return [
            'extra'   => round($extra, 2),
            'options' => $options,
            'label'   => implode(', ', array_column($options, 'choice')),
        ];
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
