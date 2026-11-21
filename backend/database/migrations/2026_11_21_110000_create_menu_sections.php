<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Timed menu sections, e.g. "Breakfast" 4–7 PM with picked dishes, shown on the website only at that time. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menu_sections')) {
            Schema::create('menu_sections', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('subtitle')->nullable();
                $table->string('emoji', 16)->nullable();
                $table->string('available_from', 5);   // "16:00"
                $table->string('available_until', 5);  // "19:00"
                $table->json('menu_item_ids')->nullable();
                $table->boolean('sell_only_in_time')->default(false);
                $table->string('outside_hours', 8)->default('show'); // when sell_only_in_time: show "Available…" or hide
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_sections');
    }
};
