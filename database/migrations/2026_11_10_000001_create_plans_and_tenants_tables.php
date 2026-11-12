<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plans')) {
            Schema::create('plans', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->decimal('price_monthly', 12, 2)->default(0);
                $table->decimal('price_yearly', 12, 2)->default(0);
                $table->integer('max_outlets')->default(1);
                $table->integer('max_users')->default(2);
                $table->integer('max_products')->default(500);
                $table->integer('max_customers')->default(1000);
                $table->json('features')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('subdomain')->unique()->index();
                $table->string('custom_domain')->nullable()->unique();
                $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
                $table->unsignedBigInteger('owner_id')->nullable()->index();
                $table->string('status', 30)->default('trial')->index();
                $table->timestamp('trial_ends_at')->nullable();
                $table->timestamp('subscription_ends_at')->nullable();
                $table->string('wa_session_id')->nullable();
                $table->json('settings')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('tenant_subscriptions')) {
            Schema::create('tenant_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
                $table->string('billing_cycle', 20)->default('monthly');
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('payment_status', 30)->default('pending')->index();
                $table->string('payment_method')->nullable();
                $table->string('gateway_reference')->nullable()->index();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('starts_at');
                $table->timestamp('ends_at');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_subscriptions');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('plans');
    }
};
