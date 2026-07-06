<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('delivery_fee_settings', function (Blueprint $table) {
            $table->renameColumn('min_order_amount', 'min_distance_km');
            $table->renameColumn('max_order_amount', 'max_distance_km');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_fee_settings', function (Blueprint $table) {
            $table->renameColumn('min_distance_km', 'min_order_amount');
            $table->renameColumn('max_distance_km', 'max_order_amount');
        });
    }
};
