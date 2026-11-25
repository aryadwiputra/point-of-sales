<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_station_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_station_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['outlet_id', 'category_id']);
            $table->index('kitchen_station_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_station_categories');
    }
};
