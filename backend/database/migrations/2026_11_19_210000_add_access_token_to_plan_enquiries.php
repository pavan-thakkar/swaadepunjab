<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('plan_enquiries', 'access_token')) {
            Schema::table('plan_enquiries', function (Blueprint $table) {
                // Secret given to the browser that sent the request, so it can see the request without logging in
                $table->string('access_token', 64)->nullable()->after('id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('plan_enquiries', 'access_token')) {
            Schema::table('plan_enquiries', fn (Blueprint $t) => $t->dropColumn('access_token'));
        }
    }
};
