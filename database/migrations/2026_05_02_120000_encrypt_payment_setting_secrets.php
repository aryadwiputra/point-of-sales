<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $fields = [
        'midtrans_server_key',
        'xendit_secret_key',
        'xendit_callback_token',
    ];

    public function up(): void
    {
        Schema::table('payment_settings', function (Blueprint $table) {
            $table->text('midtrans_server_key')->nullable()->change();
            $table->text('xendit_secret_key')->nullable()->change();
            $table->text('xendit_callback_token')->nullable()->change();
        });

        DB::table('payment_settings')
            ->select(['id', ...$this->fields])
            ->orderBy('id')
            ->get()
            ->each(function ($setting) {
                $updates = [];

                foreach ($this->fields as $field) {
                    $value = $setting->{$field};

                    if (blank($value) || $this->isEncrypted($value)) {
                        continue;
                    }

                    $updates[$field] = Crypt::encryptString($value);
                }

                if ($updates !== []) {
                    DB::table('payment_settings')->where('id', $setting->id)->update($updates);
                }
            });
    }

    public function down(): void
    {
        DB::table('payment_settings')
            ->select(['id', ...$this->fields])
            ->orderBy('id')
            ->get()
            ->each(function ($setting) {
                $updates = [];

                foreach ($this->fields as $field) {
                    $value = $setting->{$field};

                    if (blank($value) || ! $this->isEncrypted($value)) {
                        continue;
                    }

                    $updates[$field] = Crypt::decryptString($value);
                }

                if ($updates !== []) {
                    DB::table('payment_settings')->where('id', $setting->id)->update($updates);
                }
            });

        Schema::table('payment_settings', function (Blueprint $table) {
            $table->string('midtrans_server_key')->nullable()->change();
            $table->string('xendit_secret_key')->nullable()->change();
            $table->string('xendit_callback_token')->nullable()->change();
        });
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
};
