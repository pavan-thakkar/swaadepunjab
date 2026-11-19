<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('monthly_plans')) {
            Schema::create('monthly_plans', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('badge', 40)->nullable();           // e.g. "Most popular"
                $table->text('description')->nullable();
                $table->decimal('price', 10, 2);
                $table->string('price_note', 60)->default('per person / month');
                $table->string('meal_type', 20)->default('lunch');  // lunch, dinner, both, breakfast
                $table->unsignedSmallInteger('days_per_month')->nullable(); // e.g. 22 working days
                $table->json('includes')->nullable();               // ["Dal", "Sabji", "4 Roti", ...]
                $table->unsignedInteger('min_people')->nullable();
                $table->string('image')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('plan_enquiries')) {
            Schema::create('plan_enquiries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('monthly_plan_id')->nullable()->constrained()->nullOnDelete();
                $table->string('plan_name')->nullable();            // copy, in case the plan is renamed/deleted
                $table->string('name');
                $table->string('company')->nullable();
                $table->string('phone', 20);
                $table->string('email')->nullable();
                $table->unsignedInteger('people')->nullable();
                $table->date('start_date')->nullable();
                $table->text('address')->nullable();
                $table->text('message')->nullable();
                $table->string('status', 20)->default('new');       // new, contacted, active, closed
                $table->text('admin_notes')->nullable();
                $table->timestamps();
                $table->index(['status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_enquiries');
        Schema::dropIfExists('monthly_plans');
    }
};
