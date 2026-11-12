<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_addons')) {
            return;
        }

        Schema::create('tenant_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            // 'finance' | 'bihaju' (lihat App\Support\AddonCodes)
            $table->string('code', 40);
            $table->string('status', 20)->default('active')->index();
            // Pengaturan perilaku add-on (non-rahasia).
            $table->json('config')->nullable();
            // Materi rahasia per tenant (partner key, dsb.) — cast encrypted di model.
            $table->text('secret')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_addons');
    }
};
