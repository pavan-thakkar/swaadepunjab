<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'emoji',
        'image',
        'description',
        'is_active',
        'is_addon',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_addon'  => 'boolean',
    ];

    /** Dish photos in this category, best first: [storage path => dish name]. */
    public function dishPhotos(int $limit = 24): array
    {
        $out = [];
        $dishes = MenuItem::where('category', $this->slug)
            ->orderByDesc('is_available')->orderByDesc('is_featured')->orderByDesc('rating')->orderBy('name')
            ->get(['id', 'name', 'image', 'is_available', 'is_featured', 'rating']);
        foreach ($dishes as $d) {
            foreach ((array) ($d->image ?? []) as $path) {
                if (is_string($path) && $path !== '' && ! isset($out[$path])) {
                    $out[$path] = $d->name;
                    break; // first photo of each dish
                }
            }
            if (count($out) >= $limit) break;
        }
        return $out;
    }

    /** Copy a dish photo to be this category's image (a copy, so deleting the dish photo later won't break it). */
    public function useDishPhoto(string $path): bool
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        if (str_starts_with($path, 'http') || ! $disk->exists($path)) {
            return false;
        }
        $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
        $new = 'categories/' . \Illuminate\Support\Str::slug($this->slug ?: $this->name) . '-' . \Illuminate\Support\Str::random(6) . '.' . $ext;
        if (! $disk->copy($path, $new)) {
            return false;
        }
        $this->update(['image' => $new]);
        return true;
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'category', 'slug');
    }
}
