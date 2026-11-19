<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipt_settings', function (Blueprint $table) {
            $table->boolean('loyalty_enabled')->default(false);
            $table->decimal('loyalty_earn_per_100', 8, 2)->default(5);    // points per ₹100 spent
            $table->decimal('loyalty_point_value', 8, 2)->default(1);     // ₹ value of 1 point
            $table->unsignedInteger('loyalty_min_redeem')->default(50);   // min points to use
            $table->unsignedTinyInteger('loyalty_max_redeem_percent')->default(50); // max % of order paid by points
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 15)->index();       // last 10 digits of customer phone
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->integer('points');                  // + earned, − used
            $table->string('type', 20);                 // earn | redeem | refund | reverse | adjust
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('points_redeemed')->default(0);
            $table->decimal('points_discount', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['points_redeemed', 'points_discount']));
        Schema::dropIfExists('loyalty_transactions');
        Schema::table('receipt_settings', fn (Blueprint $t) => $t->dropColumn([
            'loyalty_enabled', 'loyalty_earn_per_100', 'loyalty_point_value', 'loyalty_min_redeem', 'loyalty_max_redeem_percent',
        ]));
    }
};
