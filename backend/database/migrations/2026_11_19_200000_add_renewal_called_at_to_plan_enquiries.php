<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('plan_enquiries', 'renewal_called_at')) {
            Schema::table('plan_enquiries', function (Blueprint $table) {
                // Set when someone calls the customer about renewing; cleared when the plan is renewed
                $table->timestamp('renewal_called_at')->nullable()->after('customer_note');
                $table->string('renewal_called_by')->nullable()->after('renewal_called_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('plan_enquiries', 'renewal_called_at')) {
            Schema::table('plan_enquiries', fn (Blueprint $t) => $t->dropColumn(['renewal_called_at', 'renewal_called_by']));
        }
    }
};
