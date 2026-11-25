<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->string('note', 255)->nullable()->after('price');
            $table->char('line_signature', 40)->nullable()->after('note')->index();
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropIndex(['line_signature']);
            $table->dropColumn(['note', 'line_signature']);
        });
    }
};
