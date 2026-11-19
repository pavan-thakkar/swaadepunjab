<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipt_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('receipt_settings', 'razorpay_enabled')) {
                $table->boolean('razorpay_enabled')->default(false);
                $table->string('razorpay_key_id')->nullable();
                $table->text('razorpay_key_secret')->nullable();      // stored encrypted
                $table->text('razorpay_webhook_secret')->nullable();  // stored encrypted
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'payment_status')) {
                // null = cash / pay later, 'pending' = waiting for online payment, 'paid', 'failed', 'refunded'
                $table->string('payment_status', 20)->nullable()->after('payment_method');
                $table->string('razorpay_order_id', 64)->nullable()->after('payment_status');
                $table->string('razorpay_payment_id', 64)->nullable()->after('razorpay_order_id');
                $table->timestamp('paid_at')->nullable()->after('razorpay_payment_id');
                $table->index('razorpay_order_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'payment_status')) {
                $table->dropIndex(['razorpay_order_id']);
                $table->dropColumn(['payment_status', 'razorpay_order_id', 'razorpay_payment_id', 'paid_at']);
            }
        });
        Schema::table('receipt_settings', function (Blueprint $table) {
            if (Schema::hasColumn('receipt_settings', 'razorpay_enabled')) {
                $table->dropColumn(['razorpay_enabled', 'razorpay_key_id', 'razorpay_key_secret', 'razorpay_webhook_secret']);
            }
        });
    }
};
