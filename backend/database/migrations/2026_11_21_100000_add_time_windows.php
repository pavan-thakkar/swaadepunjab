<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sell a dish (or a whole category) only at certain hours, e.g. breakfast 4–7 PM. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['menu_items', 'categories'] as $t) {
            Schema::table($t, function (Blueprint $table) use ($t) {
                if (! Schema::hasColumn($t, 'time_limited')) {
                    $table->boolean('time_limited')->default(false);
                }
                if (! Schema::hasColumn($t, 'available_from')) {
                    $table->string('available_from', 5)->nullable();   // "16:00"
                }
                if (! Schema::hasColumn($t, 'available_until')) {
                    $table->string('available_until', 5)->nullable();  // "19:00"
                }
                if (! Schema::hasColumn($t, 'outside_hours')) {
                    $table->string('outside_hours', 8)->default('show'); // show = "Available 4–7 PM", hide = not shown
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['menu_items', 'categories'] as $t) {
            foreach (['time_limited', 'available_from', 'available_until', 'outside_hours'] as $c) {
                if (Schema::hasColumn($t, $c)) {
                    Schema::table($t, fn (Blueprint $x) => $x->dropColumn($c));
                }
            }
        }
    }
};
