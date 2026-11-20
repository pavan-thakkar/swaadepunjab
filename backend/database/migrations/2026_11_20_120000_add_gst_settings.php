<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipt_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('receipt_settings', 'gst_enabled')) {
                $table->boolean('gst_enabled')->default(false);
            }
            if (! Schema::hasColumn('receipt_settings', 'gst_rate')) {
                $table->decimal('gst_rate', 5, 2)->default(5);
            }
            if (! Schema::hasColumn('receipt_settings', 'gst_mode')) {
                // exclusive = added on top of the price, inclusive = already inside the price
                $table->string('gst_mode', 12)->default('exclusive');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'gst_amount')) {
                $table->decimal('gst_amount', 10, 2)->default(0);
            }
            if (! Schema::hasColumn('orders', 'gst_rate')) {
                $table->decimal('gst_rate', 5, 2)->nullable();
            }
            if (! Schema::hasColumn('orders', 'gst_mode')) {
                $table->string('gst_mode', 12)->nullable();
            }
        });
    }

    public function down(): void
    {
        foreach (['gst_enabled', 'gst_rate', 'gst_mode'] as $c) {
            if (Schema::hasColumn('receipt_settings', $c)) {
                Schema::table('receipt_settings', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
        foreach (['gst_amount', 'gst_rate', 'gst_mode'] as $c) {
            if (Schema::hasColumn('orders', $c)) {
                Schema::table('orders', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
    }
};
