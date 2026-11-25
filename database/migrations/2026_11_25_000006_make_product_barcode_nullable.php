<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('barcode')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Rollback fails if any product currently has a NULL barcode. Backfill those first.
        Schema::table('products', function (Blueprint $table) {
            $table->string('barcode')->nullable(false)->change();
        });
    }
};
