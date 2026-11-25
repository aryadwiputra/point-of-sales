<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dine_orders', function (Blueprint $table) {
            $table->foreignId('dine_session_id')->nullable()->constrained('dine_sessions')->nullOnDelete();
            $table->string('source', 10)->default('qr');
            $table->index('dine_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('dine_orders', function (Blueprint $table) {
            $table->dropForeign(['dine_session_id']);
            $table->dropIndex(['dine_session_id']);
            $table->dropColumn(['dine_session_id', 'source']);
        });
    }
};
