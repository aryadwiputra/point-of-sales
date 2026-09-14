<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['pricing_rules', 'price_lists', 'dine_areas'] as $name) {
            if (! Schema::hasColumn($name, 'outlet_id')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->foreignId('outlet_id')->nullable()->after('id')->constrained('outlets')->nullOnDelete();
                    $table->index('outlet_id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['pricing_rules', 'price_lists', 'dine_areas'] as $name) {
            if (Schema::hasColumn($name, 'outlet_id')) {
                Schema::table($name, function (Blueprint $table) use ($name) {
                    $table->dropForeign([$name === 'dine_areas' ? 'outlet_id' : 'outlet_id']);
                    $table->dropIndex($name.'_outlet_id_index');
                    $table->dropColumn('outlet_id');
                });
            }
        }
    }
};
