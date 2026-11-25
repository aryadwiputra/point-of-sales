<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $defaults = [
            ['key' => 'store_name', 'value' => 'Dikasir', 'description' => 'Nama toko'],
            ['key' => 'store_logo', 'value' => null, 'description' => 'Logo toko'],
            ['key' => 'store_address', 'value' => 'Alamat belum diisi', 'description' => 'Alamat lengkap toko'],
            ['key' => 'store_phone', 'value' => '', 'description' => 'Nomor telepon toko'],
            ['key' => 'store_email', 'value' => '', 'description' => 'Email toko'],
            ['key' => 'store_website', 'value' => '', 'description' => 'Website atau sosial media'],
            ['key' => 'store_city', 'value' => '', 'description' => 'Kota/Kabupaten toko'],
        ];

        foreach ($defaults as $row) {
            DB::table('settings')->updateOrInsert(
                ['key' => $row['key']],
                [
                    'value' => $row['value'],
                    'description' => $row['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')
            ->whereIn('key', [
                'store_name',
                'store_logo',
                'store_address',
                'store_phone',
                'store_email',
                'store_website',
                'store_city',
            ])
            ->delete();
    }
};
