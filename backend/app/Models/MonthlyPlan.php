<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthlyPlan extends Model
{
    public const MEAL_TYPES = [
        'lunch'     => 'Lunch',
        'dinner'    => 'Dinner',
        'both'      => 'Lunch + Dinner',
        'breakfast' => 'Breakfast',
    ];

    protected $fillable = [
        'name', 'badge', 'description', 'price', 'price_note', 'meal_type',
        'days_per_month', 'includes', 'min_people', 'image', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'price'          => 'decimal:2',
        'includes'       => 'array',
        'is_active'      => 'boolean',
        'days_per_month' => 'integer',
        'min_people'     => 'integer',
        'sort_order'     => 'integer',
    ];

    public function enquiries(): HasMany
    {
        return $this->hasMany(PlanEnquiry::class);
    }

    /** Is the Monthly Plans section switched on for the website? */
    public static function sectionEnabled(): bool
    {
        return SiteSetting::getValue('monthly_plans_enabled', '0') === '1';
    }

    public static function setSectionEnabled(bool $on): void
    {
        SiteSetting::setValue('monthly_plans_enabled', $on ? '1' : '0');
    }

    public static function sectionTitle(): string
    {
        return SiteSetting::getValue('monthly_plans_title') ?: 'Monthly Plans';
    }

    public static function sectionSubtitle(): string
    {
        return SiteSetting::getValue('monthly_plans_subtitle') ?: 'Fresh Punjabi meals for your office team, every working day.';
    }

    /** Shape sent to the website. */
    public function toPublicArray(): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'badge'          => $this->badge,
            'description'    => $this->description,
            'price'          => (float) $this->price,
            'price_note'     => $this->price_note,
            'meal_type'      => $this->meal_type,
            'meal_label'     => self::MEAL_TYPES[$this->meal_type] ?? ucfirst((string) $this->meal_type),
            'days_per_month' => $this->days_per_month,
            'includes'       => array_values(array_filter(array_map('trim', array_merge(...array_map(
                fn ($i) => explode(',', is_array($i) ? (string) ($i['item'] ?? '') : (string) $i),
                array_values((array) ($this->includes ?? [])) ?: [[]]
            ))))),
            'min_people'     => $this->min_people,
            'image'          => $this->image,
        ];
    }
}
