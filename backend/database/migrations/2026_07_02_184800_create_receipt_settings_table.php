<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('receipt_settings', function (Blueprint $table) {
            $table->id();
            $table->string('outlet_name')->default('SWAD E PUNJAB');
            $table->text('outlet_address')->nullable();
            $table->string('outlet_phone')->nullable();
            $table->string('gst_number')->nullable();
            $table->string('footer_message')->nullable();
            $table->timestamps();
        });

        // Seed initial receipt configuration
        \Illuminate\Support\Facades\DB::table('receipt_settings')->insert([
            'outlet_name'    => 'SWAD E PUNJAB',
            'outlet_address' => '123, Food Street, Near Circle',
            'outlet_phone'   => '+91 98765 43210',
            'gst_number'     => null,
            'footer_message' => 'Swad E Punjab Taste of Tradition',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipt_settings');
    }
};
