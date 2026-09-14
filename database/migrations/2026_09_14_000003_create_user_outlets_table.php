<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_outlets', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'outlet_id']);
        });

        $outlet = DB::table('outlets')->where('code', 'PUSAT')->first();
        if ($outlet) {
            $now = now();
            foreach (DB::table('users')->pluck('id') as $userId) {
                DB::table('user_outlets')->insertOrIgnore([
                    'user_id' => $userId, 'outlet_id' => $outlet->id, 'is_default' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_outlets');
    }
};
