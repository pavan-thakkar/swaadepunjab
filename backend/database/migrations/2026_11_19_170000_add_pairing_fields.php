<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('categories', 'is_addon')) {
            Schema::table('categories', function (Blueprint $table) {
                // Sides & extras (breads, papad, drinks...) suggested with main dishes
                $table->boolean('is_addon')->default(false)->after('is_active');
            });
            // Start with the obvious sides on this menu
            DB::table('categories')->whereIn('slug', ['papad', 'tandoor_se'])->update(['is_addon' => true]);
        }
        if (! Schema::hasColumn('menu_items', 'pairs_with')) {
            Schema::table('menu_items', function (Blueprint $table) {
                // Dishes the owner hand-picks for "Goes well with"
                $table->json('pairs_with')->nullable()->after('option_groups');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('categories', 'is_addon')) {
            Schema::table('categories', fn (Blueprint $t) => $t->dropColumn('is_addon'));
        }
        if (Schema::hasColumn('menu_items', 'pairs_with')) {
            Schema::table('menu_items', fn (Blueprint $t) => $t->dropColumn('pairs_with'));
        }
    }
};
