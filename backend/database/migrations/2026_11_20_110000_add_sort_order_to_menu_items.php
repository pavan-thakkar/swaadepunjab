<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('menu_items', 'sort_order')) {
            Schema::table('menu_items', function (Blueprint $table) {
                // Order of the dish inside its category (drag to reorder in admin)
                $table->unsignedInteger('sort_order')->default(0)->after('category');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('menu_items', 'sort_order')) {
            Schema::table('menu_items', fn (Blueprint $t) => $t->dropColumn('sort_order'));
        }
    }
};
