<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        Setting::query()
            ->where('key', 'store_name')
            ->where('value', 'Toko Anda')
            ->get()
            ->each(fn (Setting $setting) => $setting->update(['value' => 'Dikasir']));
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        Setting::query()
            ->where('key', 'store_name')
            ->where('value', 'Dikasir')
            ->get()
            ->each(fn (Setting $setting) => $setting->update(['value' => 'Toko Anda']));
    }
};
