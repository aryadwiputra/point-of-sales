<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Paket dasar untuk 1 toko atau kios kecil UMKM.',
                'price_monthly' => 99000,
                'price_yearly' => 990000,
                'billing_type' => 'subscription',
                'max_outlets' => 1,
                'max_users' => 2,
                'max_products' => 500,
                'max_customers' => 1000,
                'features' => [
                    'basic_pos',
                    'cashier_shift',
                    'sales_report',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Paket ideal untuk bisnis berkembang hingga 3 cabang dan inventori lengkap.',
                'price_monthly' => 249000,
                'price_yearly' => 2490000,
                'billing_type' => 'subscription',
                'max_outlets' => 3,
                'max_users' => 10,
                'max_products' => 5000,
                'max_customers' => 10000,
                'features' => [
                    'basic_pos',
                    'cashier_shift',
                    'sales_report',
                    'multi_warehouse',
                    'stock_transfer',
                    'purchase_order',
                    'crm',
                    'loyalty',
                    'multi_wa',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Solusi lengkap tanpa batas cabang, integrasi API, dan pembukuan akuntansi penuh.',
                'price_monthly' => 599000,
                'price_yearly' => 5990000,
                'billing_type' => 'subscription',
                'max_outlets' => 999,
                'max_users' => 999,
                'max_products' => 999999,
                'max_customers' => 999999,
                'features' => [
                    'basic_pos',
                    'cashier_shift',
                    'sales_report',
                    'multi_warehouse',
                    'stock_transfer',
                    'purchase_order',
                    'crm',
                    'loyalty',
                    'multi_wa',
                    'accounting_journal',
                    'finance',
                    'bihaju',
                    'api_access',
                    'custom_domain',
                ],
                'is_active' => true,
            ],
            [
                // Tier keempat: tanpa langganan bulanan/tahunan — bayar per transaksi.
                // Hanya fitur inti POS yang terbuka; nilai biaya bisa diubah dari
                // Superadmin → Manajemen Paket Langganan.
                'name' => 'Bayar Per Transaksi',
                'slug' => 'pay-per-transaction',
                'description' => 'Tanpa biaya bulanan — bayar hanya saat bertransaksi. Cocok untuk usaha baru yang baru mulai jualan.',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'billing_type' => 'per_transaction',
                'fee_type' => 'percent',
                'fee_value' => 1.5,
                'fee_min' => 500,
                'fee_max' => 2000,
                'fee_rounding' => 'none',
                'fee_methods' => null, // null = semua metode bayar kena biaya
                'max_outlets' => 1,
                'max_users' => 2,
                'max_products' => 500,
                'max_customers' => 1000,
                'features' => [
                    'basic_pos',
                    'cashier_shift',
                    'sales_report',
                ],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
