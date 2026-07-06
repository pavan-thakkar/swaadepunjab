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
        Schema::table('receipt_settings', function (Blueprint $table) {
            $table->string('whatsapp_gateway_url')->nullable();
            $table->string('whatsapp_gateway_token')->nullable();
            $table->text('whatsapp_gateway_payload_template')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipt_settings', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_gateway_url', 'whatsapp_gateway_token', 'whatsapp_gateway_payload_template']);
        });
    }
};
