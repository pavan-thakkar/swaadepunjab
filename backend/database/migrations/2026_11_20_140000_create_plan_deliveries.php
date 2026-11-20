<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plan_deliveries')) {
            Schema::create('plan_deliveries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('plan_enquiry_id')->constrained('plan_enquiries')->cascadeOnDelete();
                $table->date('date');
                $table->unsignedInteger('people')->nullable();   // meals sent that day (if different from the plan)
                $table->string('status', 12)->default('delivered'); // delivered | skipped
                $table->string('note', 255)->nullable();
                $table->string('done_by', 100)->nullable();
                $table->timestamps();
                $table->unique(['plan_enquiry_id', 'date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_deliveries');
    }
};
