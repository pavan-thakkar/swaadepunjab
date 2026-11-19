<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('menu_items', 'option_groups')) {
            Schema::table('menu_items', function (Blueprint $table) {
                // [{name, type: single|multiple, required, max, choices: [{name, price, available}]}]
                $table->json('option_groups')->nullable()->after('quantity_label');
            });
        }
        if (! Schema::hasColumn('order_items', 'options')) {
            Schema::table('order_items', function (Blueprint $table) {
                // Choices the customer picked: [{group, choice, price}]
                $table->json('options')->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('menu_items', 'option_groups')) {
            Schema::table('menu_items', fn (Blueprint $t) => $t->dropColumn('option_groups'));
        }
        if (Schema::hasColumn('order_items', 'options')) {
            Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('options'));
        }
    }
};
