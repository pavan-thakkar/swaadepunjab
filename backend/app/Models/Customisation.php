<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A reusable option group ("Choose bread", "Add-ons") that many dishes can use. */
class Customisation extends Model
{
    protected $fillable = ['name', 'admin_label', 'type', 'required', 'max', 'choices', 'is_active', 'sort_order'];

    protected $casts = [
        'choices'    => 'array',
        'required'   => 'boolean',
        'is_active'  => 'boolean',
        'max'        => 'integer',
        'sort_order' => 'integer',
    ];

    /** All customisations, loaded once per request (menus can have 100+ dishes). */
    public static function library(): \Illuminate\Support\Collection
    {
        static $cache = null;
        if ($cache === null) {
            try {
                $cache = static::query()->orderBy('sort_order')->orderBy('name')->get()->keyBy('id');
            } catch (\Throwable $e) {
                $cache = collect(); // table not migrated yet
            }
        }
        return $cache;
    }

    /** Same shape as a dish's own option group. */
    public function toGroup(): array
    {
        return [
            'name'     => $this->name,
            'type'     => $this->type,
            'required' => $this->required,
            'max'      => $this->max,
            'choices'  => array_values((array) ($this->choices ?? [])),
        ];
    }

    /** Dishes that use this customisation. */
    public function dishes(): \Illuminate\Support\Collection
    {
        return MenuItem::query()->whereNotNull('customisation_ids')->get(['id', 'name', 'customisation_ids'])
            ->filter(fn (MenuItem $m) => in_array($this->id, array_map('intval', (array) $m->customisation_ids), true))
            ->values();
    }

    public function label(): string
    {
        return $this->admin_label ? "{$this->name} — {$this->admin_label}" : $this->name;
    }
}
