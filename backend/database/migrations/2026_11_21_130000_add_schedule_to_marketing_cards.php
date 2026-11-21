<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Promo banners can show only at certain times (e.g. the Evening Breakfast video 3:30–7 PM). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_cards', function (Blueprint $table) {
            if (! Schema::hasColumn('marketing_cards', 'time_limited')) {
                $table->boolean('time_limited')->default(false);
            }
            if (! Schema::hasColumn('marketing_cards', 'available_from')) {
                $table->string('available_from', 5)->nullable();
            }
            if (! Schema::hasColumn('marketing_cards', 'available_until')) {
                $table->string('available_until', 5)->nullable();
            }
        });
    }

    public function down(): void
    {
        foreach (['time_limited', 'available_from', 'available_until'] as $c) {
            if (Schema::hasColumn('marketing_cards', $c)) {
                Schema::table('marketing_cards', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
    }
};
