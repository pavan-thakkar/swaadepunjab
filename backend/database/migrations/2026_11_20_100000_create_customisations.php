<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customisations')) {
            Schema::create('customisations', function (Blueprint $table) {
                $table->id();
                $table->string('name');                         // shown to customers, e.g. "Choose bread"
                $table->string('admin_label')->nullable();      // only for admins, e.g. "Breads for dal combos"
                $table->string('type', 10)->default('single');  // single | multiple
                $table->boolean('required')->default(true);
                $table->unsignedTinyInteger('max')->nullable();
                $table->json('choices')->nullable();            // [{name, price, available}]
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
        if (! Schema::hasColumn('menu_items', 'customisation_ids')) {
            Schema::table('menu_items', function (Blueprint $table) {
                $table->json('customisation_ids')->nullable()->after('option_groups'); // saved customisations used by this dish
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('menu_items', 'customisation_ids')) {
            Schema::table('menu_items', fn (Blueprint $t) => $t->dropColumn('customisation_ids'));
        }
        Schema::dropIfExists('customisations');
    }
};
