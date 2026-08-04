<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Audit Log Descriptions
    |--------------------------------------------------------------------------
    */
    'products' => [
        'created' => 'Produk baru dibuat',
        'updated' => 'Data produk diperbarui',
        'deleted' => 'Produk dihapus',
    ],
    'categories' => [
        'created' => 'Kategori baru dibuat',
        'updated' => 'Kategori diperbarui',
        'deleted' => 'Kategori dihapus',
    ],
    'customers' => [
        'created' => 'Pelanggan baru dibuat',
        'updated' => 'Data pelanggan diperbarui',
        'deleted' => 'Pelanggan dihapus',
    ],
    'suppliers' => [
        'created' => 'Supplier baru dibuat',
        'updated' => 'Data supplier diperbarui',
        'deleted' => 'Supplier dihapus',
    ],
    'transactions' => [
        'created' => 'Transaksi baru dibuat',
        'updated' => 'Transaksi diperbarui',
        'voided' => 'Transaksi dibatalkan',
        'payment_confirmed' => 'Pembayaran transaksi dikonfirmasi',
    ],
    'cashier_shifts' => [
        'opened' => 'Shift kasir dibuka',
        'closed' => 'Shift kasir ditutup',
        'force_closed' => 'Shift kasir dipaksa ditutup',
    ],
    'stock' => [
        'adjusted' => 'Stok disesuaikan',
        'transferred' => 'Stok ditransfer',
        'opname_finalized' => 'Stock opname di-finalisasi',
    ],
    'purchase_orders' => [
        'created' => 'Purchase Order dibuat',
        'placed' => 'Purchase Order di-order',
        'cancelled' => 'Purchase Order dibatalkan',
    ],
    'users' => [
        'created' => 'User baru dibuat',
        'updated' => 'Data user diperbarui',
        'deleted' => 'User dihapus',
        'role_changed' => 'Role user diubah',
    ],
    'roles' => [
        'created' => 'Role baru dibuat',
        'updated' => 'Role diperbarui',
        'deleted' => 'Role dihapus',
        'permission_changed' => 'Permission role diubah',
    ],
    'settings' => [
        'updated' => 'Pengaturan diperbarui',
    ],
    'bank_accounts' => [
        'created' => 'Rekening bank ditambahkan',
        'updated' => 'Rekening bank diperbarui',
        'deleted' => 'Rekening bank dihapus',
    ],
    'payment_settings' => [
        'updated' => 'Pengaturan payment diperbarui',
    ],
];
