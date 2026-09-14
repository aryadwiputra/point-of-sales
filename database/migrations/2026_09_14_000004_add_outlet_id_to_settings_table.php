<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('settings', 'outlet_id')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->foreignId('outlet_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });

            Schema::table('settings', function (Blueprint $table) {
                $table->dropUnique('settings_key_unique');
                $table->unique(['key', 'outlet_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('settings', 'outlet_id')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->dropUnique('settings_key_outlet_id_unique');
                $table->dropConstrainedForeignId('outlet_id');
                $table->unique('key');
            });
        }
    }
};
