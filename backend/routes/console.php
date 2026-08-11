<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('menu:assign-images', function () {
    $service = new \App\Services\MenuImportService();
    $items = \App\Models\MenuItem::all();
    $count = 0;
    foreach ($items as $item) {
        $itemImage = $item->image;
        if (empty($itemImage) || (is_array($itemImage) && count($itemImage) === 0)) {
            $item->image = $service->resolveItemImage($item->name, $item->category ?? '', $item->description ?? '');
            $item->save();
            $count++;
        }
    }
    $this->info("Successfully assigned images to {$count} menu items.");
})->purpose('Assign default high quality food photos to menu items without images');

