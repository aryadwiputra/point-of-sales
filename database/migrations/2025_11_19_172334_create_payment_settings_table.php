<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('default_gateway')->default('cash');
            $table->boolean('midtrans_enabled')->default(false);
            $table->string('midtrans_server_key')->nullable();
            $table->string('midtrans_client_key')->nullable();
            $table->boolean('midtrans_production')->default(false);
            $table->boolean('xendit_enabled')->default(false);
            $table->string('xendit_secret_key')->nullable();
            $table->string('xendit_public_key')->nullable();
            $table->boolean('xendit_production')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_settings');
    }
};
