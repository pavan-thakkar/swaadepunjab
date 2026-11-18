<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('description')->nullable();
            $table->string('discount_type'); // flat | percent
            $table->decimal('discount_value', 10, 2);
            $table->decimal('max_discount', 10, 2)->nullable();
            $table->decimal('min_order_amount', 10, 2)->default(0);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_customer_limit')->nullable()->default(1);
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('combine_with_offers')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('coupon_code', 40)->nullable();
            $table->decimal('coupon_discount', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['coupon_code', 'coupon_discount']));
        Schema::dropIfExists('coupons');
    }
};
