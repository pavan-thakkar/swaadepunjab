<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Who cancelled an order: the customer, a staff member (name kept) or the system (online payment not completed). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'cancelled_by')) $table->string('cancelled_by', 10)->nullable();
            if (! Schema::hasColumn('orders', 'cancelled_by_name')) $table->string('cancelled_by_name', 100)->nullable();
            if (! Schema::hasColumn('orders', 'cancelled_at')) $table->timestamp('cancelled_at')->nullable();
            if (! Schema::hasColumn('orders', 'cancel_reason')) $table->string('cancel_reason', 255)->nullable();
        });

        // Fill in old cancelled orders from the activity log (best guess)
        try {
            if (! Schema::hasTable('activity_logs')) return;
            $orders = DB::table('orders')->where('status', 'cancelled')->whereNull('cancelled_by')->get(['id', 'payment_method']);
            foreach ($orders as $o) {
                $log = DB::table('activity_logs')->where('subject_type', 'Order')->where('subject_id', $o->id)
                    ->where('changes', 'like', '%cancelled%')->orderByDesc('id')->first();
                if (! $log) continue;
                $ch = json_decode((string) $log->changes, true) ?: [];
                if (($ch['status']['to'] ?? null) !== 'cancelled') continue;
                if (isset($ch['edited_at'])) {
                    $by = ['customer', null, 'Cancelled from the website'];
                } elseif (($ch['payment_status']['to'] ?? null) === 'failed') {
                    $by = ['system', null, 'Online payment not completed'];
                } elseif ($log->user_id) {
                    $by = ['staff', $log->user_name, null];
                } else {
                    $by = ['customer', null, null];
                }
                DB::table('orders')->where('id', $o->id)->update(['cancelled_by' => $by[0], 'cancelled_by_name' => $by[1], 'cancel_reason' => $by[2], 'cancelled_at' => $log->created_at]);
            }
        } catch (\Throwable $e) {
            // not important enough to stop the deploy
        }
    }

    public function down(): void
    {
        foreach (['cancelled_by', 'cancelled_by_name', 'cancelled_at', 'cancel_reason'] as $c) {
            if (Schema::hasColumn('orders', $c)) Schema::table('orders', fn (Blueprint $t) => $t->dropColumn($c));
        }
    }
};
