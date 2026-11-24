<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Shop GPS location (centre of the delivery area). Starts at the old fixed point: Maple 99 Food County, GIFT City. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipt_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('receipt_settings', 'outlet_latitude')) $table->decimal('outlet_latitude', 10, 7)->nullable();
            if (! Schema::hasColumn('receipt_settings', 'outlet_longitude')) $table->decimal('outlet_longitude', 10, 7)->nullable();
        });
        DB::table('receipt_settings')->whereNull('outlet_latitude')->update(['outlet_latitude' => 23.1648274, 'outlet_longitude' => 72.6826934]);
    }

    public function down(): void
    {
        foreach (['outlet_latitude', 'outlet_longitude'] as $c) {
            if (Schema::hasColumn('receipt_settings', $c)) Schema::table('receipt_settings', fn (Blueprint $t) => $t->dropColumn($c));
        }
    }
};
