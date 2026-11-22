<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Delivery boys: who carries the order, pick-up / delivered times, and "pay online at the door" links. Shop UPI ID for the rider's QR. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'rider_id')) $table->unsignedBigInteger('rider_id')->nullable()->index();
            if (! Schema::hasColumn('orders', 'rider_name')) $table->string('rider_name', 100)->nullable();
            if (! Schema::hasColumn('orders', 'rider_assigned_at')) $table->timestamp('rider_assigned_at')->nullable();
            if (! Schema::hasColumn('orders', 'picked_up_at')) $table->timestamp('picked_up_at')->nullable();
            if (! Schema::hasColumn('orders', 'delivered_at')) $table->timestamp('delivered_at')->nullable();
            if (! Schema::hasColumn('orders', 'pay_link_id')) $table->string('pay_link_id', 40)->nullable()->index();
            if (! Schema::hasColumn('orders', 'pay_link_url')) $table->string('pay_link_url', 255)->nullable();
        });
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'phone')) $table->string('phone', 15)->nullable();
        });
        Schema::table('receipt_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('receipt_settings', 'shop_upi_id')) $table->string('shop_upi_id', 100)->nullable();
            if (! Schema::hasColumn('receipt_settings', 'shop_upi_name')) $table->string('shop_upi_name', 100)->nullable();
        });
    }

    public function down(): void
    {
        foreach (['rider_id', 'rider_name', 'rider_assigned_at', 'picked_up_at', 'delivered_at', 'pay_link_id', 'pay_link_url'] as $c) {
            if (Schema::hasColumn('orders', $c)) Schema::table('orders', fn (Blueprint $t) => $t->dropColumn($c));
        }
        if (Schema::hasColumn('users', 'phone')) Schema::table('users', fn (Blueprint $t) => $t->dropColumn('phone'));
        foreach (['shop_upi_id', 'shop_upi_name'] as $c) {
            if (Schema::hasColumn('receipt_settings', $c)) Schema::table('receipt_settings', fn (Blueprint $t) => $t->dropColumn($c));
        }
    }
};
