<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dine_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dine_table_id')->constrained('dine_tables')->restrictOnDelete();
            $table->string('status', 16)->default('open');
            $table->unsignedSmallInteger('guest_count')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('merged_into_session_id')->nullable()->constrained('dine_sessions')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['dine_table_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dine_sessions');
    }
};
