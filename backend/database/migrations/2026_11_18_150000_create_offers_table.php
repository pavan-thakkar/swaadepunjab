<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('description')->nullable();
            $table->decimal('min_order_amount', 10, 2);
            $table->string('reward_type'); // free_item | flat_discount | percent_discount
            $table->foreignId('free_menu_item_id')->nullable()->constrained('menu_items')->nullOnDelete();
            $table->unsignedSmallInteger('free_quantity')->default(1);
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->decimal('max_discount', 10, 2)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('discount', 10, 2)->default(0);
            $table->foreignId('offer_id')->nullable();
            $table->string('offer_title')->nullable();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('is_free')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('is_free'));
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['discount', 'offer_id', 'offer_title']));
        Schema::dropIfExists('offers');
    }
};
