<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::set('store_npwp', '', 'NPWP Toko');
        Setting::set('store_nib', '', 'NIB Toko');
        Setting::set('tax_default_rate', '11.00', 'Default tarif PPN (%)');
    }

    public function down(): void
    {
        Setting::whereIn('key', ['store_npwp', 'store_nib', 'tax_default_rate'])->delete();
    }
};
