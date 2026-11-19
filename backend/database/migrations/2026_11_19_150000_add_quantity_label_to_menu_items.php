<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('menu_items', 'quantity_label')) {
            Schema::table('menu_items', function (Blueprint $table) {
                // Optional portion size shown to customers, e.g. "250 g", "4 pieces", "Serves 2"
                $table->string('quantity_label', 60)->nullable()->after('price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('menu_items', 'quantity_label')) {
            Schema::table('menu_items', function (Blueprint $table) {
                $table->dropColumn('quantity_label');
            });
        }
    }
};
