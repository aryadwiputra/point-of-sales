<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_station_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_station_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['kitchen_station_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_station_user');
    }
};
