<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Settle Bill: how each bill was actually paid (cash / UPI / card / split) and who settled it. Plus the nightly cash-up (hisab). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'settle_mode')) $table->string('settle_mode', 10)->nullable()->index();
            if (! Schema::hasColumn('orders', 'settle_cash')) $table->decimal('settle_cash', 10, 2)->default(0);
            if (! Schema::hasColumn('orders', 'settle_upi')) $table->decimal('settle_upi', 10, 2)->default(0);
            if (! Schema::hasColumn('orders', 'settle_card')) $table->decimal('settle_card', 10, 2)->default(0);
            if (! Schema::hasColumn('orders', 'settled_at')) $table->timestamp('settled_at')->nullable()->index();
            if (! Schema::hasColumn('orders', 'settled_by')) $table->unsignedBigInteger('settled_by')->nullable();
            if (! Schema::hasColumn('orders', 'settled_by_name')) $table->string('settled_by_name', 100)->nullable();
            if (! Schema::hasColumn('orders', 'settle_note')) $table->string('settle_note', 255)->nullable();
        });

        if (! Schema::hasTable('day_closings')) {
            Schema::create('day_closings', function (Blueprint $table) {
                $table->id();
                $table->date('business_date')->unique();
                $table->decimal('opening_cash', 10, 2)->default(0);
                $table->decimal('expenses', 10, 2)->default(0);
                $table->decimal('expected_cash', 10, 2)->default(0);
                $table->decimal('counted_cash', 10, 2)->default(0);
                $table->decimal('cash_difference', 10, 2)->default(0);
                $table->decimal('upi_counted', 10, 2)->nullable();
                $table->decimal('card_counted', 10, 2)->nullable();
                $table->json('totals')->nullable();
                $table->string('note', 500)->nullable();
                $table->unsignedBigInteger('closed_by')->nullable();
                $table->string('closed_by_name', 100)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('day_closings');
        foreach (['settle_mode', 'settle_cash', 'settle_upi', 'settle_card', 'settled_at', 'settled_by', 'settled_by_name', 'settle_note'] as $c) {
            if (Schema::hasColumn('orders', $c)) Schema::table('orders', fn (Blueprint $t) => $t->dropColumn($c));
        }
    }
};
