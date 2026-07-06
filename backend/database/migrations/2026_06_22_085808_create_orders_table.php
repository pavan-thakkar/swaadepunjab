<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone');
            $table->text('delivery_address');
            $table->string('city');
            $table->decimal('subtotal', 8, 2);
            $table->decimal('delivery_fee', 8, 2)->default(2.99);
            $table->decimal('total', 8, 2);
            $table->enum('status', [
                'pending',
                'accepted',
                'preparing',
                'out_for_delivery',
                'delivered',
                'cancelled'
            ])->default('pending');
            $table->string('payment_method')->default('cash_on_delivery');
            $table->text('special_instructions')->nullable();
            $table->timestamp('estimated_delivery_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
