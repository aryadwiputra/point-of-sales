<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payment_settings', 'outlet_id')) {
            Schema::table('payment_settings', function (Blueprint $table) {
                $table->foreignId('outlet_id')->nullable()->after('id')->constrained()->nullOnDelete();
                $table->unique('outlet_id');
            });
        }

        if (! Schema::hasColumn('bank_accounts', 'outlet_id')) {
            Schema::table('bank_accounts', function (Blueprint $table) {
                $table->foreignId('outlet_id')->nullable()->after('id')->constrained()->nullOnDelete();
                $table->index(['outlet_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payment_settings', 'outlet_id')) {
            Schema::table('payment_settings', function (Blueprint $table) {
                $table->dropUnique('payment_settings_outlet_id_unique');
                $table->dropConstrainedForeignId('outlet_id');
            });
        }

        if (Schema::hasColumn('bank_accounts', 'outlet_id')) {
            Schema::table('bank_accounts', function (Blueprint $table) {
                $table->dropIndex('bank_accounts_outlet_id_is_active_index');
                $table->dropConstrainedForeignId('outlet_id');
            });
        }
    }
};
