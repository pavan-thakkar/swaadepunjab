<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Video-only banners don't need a tag or title
    public function up(): void
    {
        Schema::table('marketing_cards', function (Blueprint $table) {
            $table->string('tag')->nullable()->default(null)->change();
            $table->string('title')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('marketing_cards', function (Blueprint $table) {
            $table->string('tag')->default('PROMO')->change();
            $table->string('title')->change();
        });
    }
};
