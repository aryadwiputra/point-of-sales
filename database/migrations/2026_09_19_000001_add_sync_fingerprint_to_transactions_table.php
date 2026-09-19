<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('sync_fingerprint')->nullable()->after('client_uuid');
            $table->index('sync_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['sync_fingerprint']);
            $table->dropColumn('sync_fingerprint');
        });
    }
};
