<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Setting::set('discount_approval_threshold', '0', 'Nominal diskon maksimal tanpa approval. 0 = nonaktif');
        Setting::set('discount_approval_percent_threshold', '0', 'Persentase diskon maksimal tanpa approval. 0 = nonaktif');
        Setting::set('discount_approval_timeout', '300', 'Timeout approval dalam detik');

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('discount_approved_by')->nullable()->constrained('users');
            $table->timestamp('discount_approved_at')->nullable();
            $table->string('discount_approval_status', 20)->nullable();
        });

        Schema::create('discount_approval_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained();
            $table->foreignId('cashier_id')->constrained('users');
            $table->bigInteger('requested_discount');
            $table->string('status', 20);
            $table->foreignId('responded_by')->nullable()->constrained('users');
            $table->timestamp('responded_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_approval_logs');
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['discount_approved_by']);
            $table->dropColumn(['discount_approved_by', 'discount_approved_at', 'discount_approval_status']);
        });
        Setting::whereIn('key', ['discount_approval_threshold', 'discount_approval_percent_threshold', 'discount_approval_timeout'])->delete();
    }
};
