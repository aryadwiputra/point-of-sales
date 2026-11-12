<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('tenant_subscriptions', 'payment_url')) {
                $table->text('payment_url')->nullable()->after('gateway_reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_subscriptions', 'payment_url')) {
                $table->dropColumn('payment_url');
            }
        });
    }
};
