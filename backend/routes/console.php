<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('menu:assign-images', function () {
    $count = \App\Models\MenuItem::query()->update(['image' => null]);
    $this->info("Successfully cleared images from {$count} menu items.");
})->purpose('Clear images from all menu items');

