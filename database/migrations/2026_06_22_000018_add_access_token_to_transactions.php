<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('access_token', 36)->nullable()->unique()->after('invoice');
        });

        Schema::table('receivables', function (Blueprint $table) {
            $table->string('access_token', 36)->nullable()->unique()->after('invoice');
        });
    }

    public function down(): void
    {
        Schema::table('receivables', function (Blueprint $table) {
            $table->dropColumn('access_token');
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('access_token');
        });
    }
};
