<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Swiggy-style flow: when the order was accepted / ready (for the prep countdown), and auto-reject minutes. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'accepted_at')) $table->timestamp('accepted_at')->nullable();
            if (! Schema::hasColumn('orders', 'ready_at')) $table->timestamp('ready_at')->nullable();
        });
        Schema::table('receipt_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('receipt_settings', 'auto_reject_minutes')) $table->unsignedSmallInteger('auto_reject_minutes')->default(0);
        });
    }

    public function down(): void
    {
        foreach (['accepted_at', 'ready_at'] as $c) {
            if (Schema::hasColumn('orders', $c)) Schema::table('orders', fn (Blueprint $t) => $t->dropColumn($c));
        }
        if (Schema::hasColumn('receipt_settings', 'auto_reject_minutes')) Schema::table('receipt_settings', fn (Blueprint $t) => $t->dropColumn('auto_reject_minutes'));
    }
};
