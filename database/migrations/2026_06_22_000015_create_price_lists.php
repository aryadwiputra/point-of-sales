<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('customer_scope', 30)->default('all');
            $table->foreignId('customer_segment_id')->nullable()->constrained('customer_segments');
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('price_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('price');
            $table->timestamps();
            $table->unique(['price_list_id', 'product_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('price_list_id')->nullable()->constrained('price_lists');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['price_list_id']);
            $table->dropColumn('price_list_id');
        });
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_lists');
    }
};
