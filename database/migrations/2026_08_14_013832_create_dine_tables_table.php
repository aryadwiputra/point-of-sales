<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dine_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dine_area_id')->nullable()->constrained('dine_areas')->nullOnDelete();
            $table->string('name');
            $table->string('token')->unique();
            $table->integer('capacity')->default(0);
            $table->integer('pos_x')->default(0);
            $table->integer('pos_y')->default(0);
            $table->string('shape')->default('circle');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('token');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dine_tables');
    }
};
