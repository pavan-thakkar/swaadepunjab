<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_cards', function (Blueprint $table) {
            if (! Schema::hasColumn('marketing_cards', 'video_path')) {
                // Uploaded MP4 (used instead of video_url when present)
                $table->string('video_path')->nullable()->after('video_url');
            }
            if (! Schema::hasColumn('marketing_cards', 'video_sound')) {
                // Show the 🔊 button so customers can turn the sound on
                $table->boolean('video_sound')->default(false)->after('video_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketing_cards', function (Blueprint $table) {
            foreach (['video_path', 'video_sound'] as $c) {
                if (Schema::hasColumn('marketing_cards', $c)) $table->dropColumn($c);
            }
        });
    }
};
