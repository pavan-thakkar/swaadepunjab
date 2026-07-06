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
        Schema::table('receipt_settings', function (Blueprint $table) {
            $table->string('google_maps_api_key')->nullable();
            $table->decimal('delivery_base_km', 8, 2)->default(3.00);
            $table->decimal('delivery_base_fee', 8, 2)->default(30.00);
            $table->decimal('delivery_extra_fee_per_km', 8, 2)->default(10.00);
            $table->decimal('delivery_max_distance', 8, 2)->default(15.00);
            $table->decimal('delivery_fallback_fee', 8, 2)->default(50.00);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipt_settings', function (Blueprint $table) {
            $table->dropColumn([
                'google_maps_api_key',
                'delivery_base_km',
                'delivery_base_fee',
                'delivery_extra_fee_per_km',
                'delivery_max_distance',
                'delivery_fallback_fee'
            ]);
        });
    }
};
