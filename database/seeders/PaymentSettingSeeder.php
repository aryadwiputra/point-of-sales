<?php

namespace Database\Seeders;

use App\Models\PaymentSetting;
use Illuminate\Database\Seeder;

class PaymentSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PaymentSetting::firstOrCreate([], [
            'default_gateway' => 'cash',
            'midtrans_enabled' => false,
            'xendit_enabled' => false,
        ]);
    }
}
