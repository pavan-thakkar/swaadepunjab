<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipt_settings', function (Blueprint $table) {
            $table->boolean('delivery_enabled')->default(true);
            $table->boolean('delivery_hours_enabled')->default(false);
            $table->time('delivery_start_time')->nullable();
            $table->time('delivery_end_time')->nullable();
            $table->string('delivery_off_message')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('receipt_settings', function (Blueprint $table) {
            $table->dropColumn(['delivery_enabled', 'delivery_hours_enabled', 'delivery_start_time', 'delivery_end_time', 'delivery_off_message']);
        });
    }
};
