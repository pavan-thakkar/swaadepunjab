<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('plan_enquiries', 'active_from')) {
            return;
        }
        Schema::table('plan_enquiries', function (Blueprint $table) {
            // Filled by the owner once the customer is confirmed (status = active). The customer sees these.
            $table->date('active_from')->nullable()->after('status');
            $table->date('active_until')->nullable()->after('active_from');
            $table->unsignedInteger('agreed_people')->nullable()->after('active_until');
            $table->decimal('agreed_price', 10, 2)->nullable()->after('agreed_people');  // total for the period
            $table->decimal('amount_paid', 10, 2)->default(0)->after('agreed_price');
            $table->string('payment_status', 20)->default('pending')->after('amount_paid'); // pending, partial, paid
            $table->string('delivery_time', 60)->nullable()->after('payment_status');
            $table->text('customer_note')->nullable()->after('delivery_time');
            $table->index(['phone']);
        });
    }

    public function down(): void
    {
        Schema::table('plan_enquiries', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->dropColumn(['active_from', 'active_until', 'agreed_people', 'agreed_price', 'amount_paid', 'payment_status', 'delivery_time', 'customer_note']);
        });
    }
};
