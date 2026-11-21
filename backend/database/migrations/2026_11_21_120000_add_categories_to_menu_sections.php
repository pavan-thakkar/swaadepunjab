<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Timed menus can include whole categories (all their dishes), not only single dishes. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('menu_sections', 'category_slugs')) {
            Schema::table('menu_sections', function (Blueprint $table) {
                $table->json('category_slugs')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('menu_sections', 'category_slugs')) {
            Schema::table('menu_sections', fn (Blueprint $t) => $t->dropColumn('category_slugs'));
        }
    }
};
