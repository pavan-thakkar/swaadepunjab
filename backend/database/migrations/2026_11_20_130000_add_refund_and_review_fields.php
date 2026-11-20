<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'refund_amount')) {
                $table->decimal('refund_amount', 10, 2)->default(0);
            }
            if (! Schema::hasColumn('orders', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable();
            }
            if (! Schema::hasColumn('orders', 'razorpay_refund_id')) {
                $table->string('razorpay_refund_id', 64)->nullable();
            }
            if (! Schema::hasColumn('orders', 'refund_note')) {
                $table->string('refund_note', 255)->nullable();
            }
            // Order rating from the customer (link sent after delivery)
            if (! Schema::hasColumn('orders', 'order_rating')) {
                $table->unsignedTinyInteger('order_rating')->nullable();
            }
            if (! Schema::hasColumn('orders', 'order_feedback')) {
                $table->text('order_feedback')->nullable();
            }
            if (! Schema::hasColumn('orders', 'rated_at')) {
                $table->timestamp('rated_at')->nullable();
            }
            if (! Schema::hasColumn('orders', 'rating_link_sent_at')) {
                $table->timestamp('rating_link_sent_at')->nullable();
            }
            if (! Schema::hasColumn('orders', 'feedback_called_at')) {
                $table->timestamp('feedback_called_at')->nullable();
            }
            if (! Schema::hasColumn('orders', 'feedback_called_by')) {
                $table->string('feedback_called_by', 100)->nullable();
            }
            if (! Schema::hasColumn('orders', 'feedback_note')) {
                $table->string('feedback_note', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        foreach (['refund_amount', 'refunded_at', 'razorpay_refund_id', 'refund_note', 'order_rating', 'order_feedback', 'rated_at', 'rating_link_sent_at', 'feedback_called_at', 'feedback_called_by', 'feedback_note'] as $c) {
            if (Schema::hasColumn('orders', $c)) {
                Schema::table('orders', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
    }
};
