<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class DineInSettingsSeeder extends Seeder
{
    public function run(): void
    {
        Setting::setMany([
            'dine_in_enabled' => [
                'value' => '1',
                'description' => 'Aktifkan fitur dine-in QR menu',
            ],
            'dine_in_self_order_enabled' => [
                'value' => '1',
                'description' => 'Izinkan pelanggan memesan langsung dari QR menu',
            ],
            'dine_in_pay_online_enabled' => [
                'value' => '1',
                'description' => 'Izinkan pembayaran online via QR menu',
            ],
        ]);
    }
}
