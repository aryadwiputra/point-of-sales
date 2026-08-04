<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PDF Labels - Common
    |--------------------------------------------------------------------------
    */
    'common' => [
        'no' => 'No.',
        'date' => 'Tanggal',
        'time' => 'Waktu',
        'status' => 'Status',
        'action' => 'Aksi',
        'total' => 'Total',
        'subtotal' => 'Subtotal',
        'tax' => 'Pajak',
        'discount' => 'Diskon',
        'payment' => 'Pembayaran',
        'paid' => 'Lunas',
        'unpaid' => 'Belum Lunas',
        'partial' => 'Sebagian',
        'due_date' => 'Jatuh Tempo',
        'customer' => 'Pelanggan',
        'cashier' => 'Kasir',
        'cash' => 'Tunai',
        'change' => 'Kembalian',
        'note' => 'Catatan',
        'thank_you' => 'Terima kasih!',
        'thank_you_message' => 'Terima kasih atas kepercayaan Anda.',
        'phone' => 'Telp',
        'email' => 'Email',
        'address' => 'Alamat',
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Labels - Invoice
    |--------------------------------------------------------------------------
    */
    'invoice' => [
        'title' => 'INVOICE',
        'invoice_number' => 'No. Invoice',
        'invoice_date' => 'Tanggal Invoice',
        'due_date' => 'Jatuh Tempo',
        'payment_status' => 'Status Pembayaran',
        'payment_method' => 'Metode Pembayaran',
        'product_name' => 'Nama Produk',
        'quantity' => 'Qty',
        'unit_price' => 'Harga Satuan',
        'subtotal_col' => 'Subtotal',
        'grand_total' => 'TOTAL',
        'tax_amount' => 'Jumlah PPN',
        'shipping_cost' => 'Ongkos Kirim',
        'promo_discount' => 'Diskon Promo',
        'manual_discount' => 'Diskon Manual',
        'voucher_discount' => 'Voucher',
        'redeem_points' => 'Tukar Poin',
        'loyalty_discount' => 'Diskon Loyalty',
        'balance_due' => 'Sisa Tagihan',
        'amount_paid' => 'Jumlah Dibayar',
        'customer_npwp' => 'NPWP Pelanggan',
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Labels - Receipt
    |--------------------------------------------------------------------------
    */
    'receipt' => [
        'title' => 'STRUK PENJUALAN',
        'transaction_no' => 'No. Transaksi',
        'transaction_date' => 'Tanggal',
        'shift' => 'Shift',
        'warehouse' => 'Gudang',
        'customer_type' => 'Umum',
        'member' => 'Member',
        'barcode' => 'Barcode',
        'product' => 'Produk',
        'unit' => 'Satuan',
        'price' => 'Harga',
        'qty' => 'Qty',
        'subtotal_col' => 'Subtotal',
        'promo' => 'Promo',
        'items_total' => 'Total Item',
        'total_discount' => 'Total Diskon',
        'before_tax' => 'Sebelum PPN',
        'tax_rate' => 'PPN :rate%',
        'grand_total' => 'TOTAL',
        'payment_method' => 'Bayar via',
        'cash_payment' => 'Tunai',
        'card_payment' => 'Kartu',
        'amount_tendered' => 'Bayar',
        'change_col' => 'Kembali',
        'points_earned' => 'Poin Didapat',
        'points_redeemed' => 'Poin Ditukar',
        'voucher_used' => 'Voucher',
        'footer' => 'Barang yang sudah dibeli tidak dapat dikembalikan.',
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Labels - Shipping Label
    |--------------------------------------------------------------------------
    */
    'shipping' => [
        'title' => 'LABEL PENGIRIMAN',
        'ship_from' => 'Pengirim',
        'ship_to' => 'Penerima',
        'weight' => 'Berat',
        'dimensions' => 'Ukuran',
        'shipping_method' => 'Metode Pengiriman',
        'tracking_number' => 'No. Resi',
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Labels - Receivable
    |--------------------------------------------------------------------------
    */
    'receivable' => [
        'title' => 'INVOICE PIUTANG',
        'original_amount' => 'Jumlah Awal',
        'amount_paid' => 'Sudah Dibayar',
        'remaining_balance' => 'Sisa Piutang',
        'overdue_days' => 'Hari Terlambat',
        'payment_history' => 'Riwayat Pembayaran',
        'payment_date' => 'Tanggal Bayar',
        'payment_amount' => 'Jumlah Bayar',
        'collector_notes' => 'Catatan Penagihan',
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Labels - Payable
    |--------------------------------------------------------------------------
    */
    'payable' => [
        'title' => 'INVOICE HUTANG',
        'supplier' => 'Supplier',
        'original_amount' => 'Jumlah Awal',
        'amount_paid' => 'Sudah Dibayar',
        'remaining_balance' => 'Sisa Hutang',
        'overdue_days' => 'Hari Terlambat',
        'payment_history' => 'Riwayat Pembayaran',
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Labels - Status
    |--------------------------------------------------------------------------
    */
    'status' => [
        'pending' => 'Menunggu',
        'processing' => 'Diproses',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
        'paid' => 'Lunas',
        'partial' => 'Sebagian',
        'overdue' => 'Jatuh Tempo',
    ],
];
