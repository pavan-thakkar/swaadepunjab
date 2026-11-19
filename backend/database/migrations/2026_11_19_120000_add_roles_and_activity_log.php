<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('staff');   // owner | manager | staff
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
        });
        // Everyone who already had an admin login stays a full-access owner
        DB::table('users')->update(['role' => 'owner']);

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable();     // kept even if the account is deleted
            $table->string('action', 30);                // login | created | updated | deleted | status
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description', 500);
            $table->json('changes')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'is_active', 'last_login_at']));
    }
};
