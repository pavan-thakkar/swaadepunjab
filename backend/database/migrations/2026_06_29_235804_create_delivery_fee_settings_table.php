<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_fee_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_order_amount', 8, 2)->default(0.00);
            $table->decimal('max_order_amount', 8, 2)->nullable();
            $table->decimal('delivery_fee', 8, 2)->default(0.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_fee_settings');
    }
};
