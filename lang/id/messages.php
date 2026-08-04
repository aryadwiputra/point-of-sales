<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Flash Messages - Authentication
    |--------------------------------------------------------------------------
    */
    'auth' => [
        'login' => 'Berhasil masuk.',
        'logout' => 'Berhasil keluar.',
        'failed' => 'Email atau password salah.',
        'throttle' => 'Terlalu banyak percobaan masuk. Silakan coba lagi dalam :seconds detik.',
        'password_reset_link_sent' => 'Link reset password telah dikirim ke email Anda.',
        'reset' => 'Password Anda telah direset.',
        'confirm_password' => 'Password yang Anda masukkan salah.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flash Messages - General
    |--------------------------------------------------------------------------
    */
    'general' => [
        'created' => 'Data berhasil dibuat.',
        'updated' => 'Data berhasil diperbarui.',
        'deleted' => 'Data berhasil dihapus.',
        'saved' => 'Data berhasil disimpan.',
        'error' => 'Terjadi kesalahan. Silakan coba lagi.',
        'not_found' => 'Data tidak ditemukan.',
        'required' => 'Field ini wajib diisi.',
        'success' => 'Operasi berhasil.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flash Messages - Products
    |--------------------------------------------------------------------------
    */
    'products' => [
        'created' => 'Produk baru berhasil ditambahkan.',
        'updated' => 'Data produk berhasil diperbarui.',
        'deleted' => 'Produk berhasil dihapus.',
        'not_found' => 'Produk tidak ditemukan.',
        'import_success' => 'Import produk berhasil. :count produk diimpor.',
        'import_error' => 'Gagal import produk. Silakan periksa format file.',
        'export_success' => 'Export produk berhasil.',
        'barcode_generated' => 'Barcode berhasil di-generate.',
        'stock_insufficient' => 'Stok tidak mencukupi.',
        'min_stock_reached' => 'Stok minimum tercapai.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flash Messages - Categories
    |--------------------------------------------------------------------------
    */
    'categories' => [
        'created' => 'Kategori berhasil ditambahkan.',
        'updated' => 'Kategori berhasil diperbarui.',
        'deleted' => 'Kategori berhasil dihapus.',
        'not_found' => 'Kategori tidak ditemukan.',
        'has_products' => 'Kategori tidak dapat dihapus karena masih memiliki produk.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flash Messages - Customers
    |--------------------------------------------------------------------------
    */
    'customers' => [
        'created' => 'Pelanggan berhasil ditambahkan.',
        'updated' => 'Data pelanggan berhasil diperbarui.',
        'deleted' => 'Pelanggan berhasil dihapus.',
        'not_found' => 'Pelanggan tidak ditemukan.',
        'import_success' => 'Import pelanggan berhasil.',
        'import_error' => 'Gagal import pelanggan.',
        'segments_synced' => 'Segment manual pelanggan berhasil diperbarui.',
        'upgraded_to_member' => 'Pelanggan berhasil ditingkatkan menjadi member.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flash Messages - Suppliers
    |--------------------------------------------------------------------------
    */
    'suppliers' => [
        'created' => 'Supplier berhasil ditambahkan.',
        'updated' => 'Data supplier berhasil diperbarui.',
        'deleted' => 'Supplier berhasil dihapus.',
        'not_found' => 'Supplier tidak ditemukan.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flash Messages - Transactions
    |--------------------------------------------------------------------------
    */
    'transactions' => [
        'created' => 'Transaksi berhasil disimpan.',
        'product_added' => 'Produk berhasil ditambahkan.',
        'product_not_found' => 'Produk tidak ditemukan.',
        'stock_insufficient' => 'Stok tidak mencukupi.',
        'cart_empty' => 'Keranjang kosong.',
        'hold_success' => 'Transaksi ditahan: :label',
        'resume_success' => 'Transaksi dilanjutkan.',
        'hold_cleared' => 'Transaksi ditahan berhasil dihapus.',
        'hold_not_found' => 'Transaksi ditahan tidak ditemukan.',
        'active_cart_exists' => 'Selesaikan atau tahan transaksi aktif terlebih dahulu.',
        'pending_approval' => 'Transaksi menunggu approval supervisor.',
        'payment_confirmed' => 'Pembayaran berhasil dikonfirmasi.',
        'already_paid' => 'Transaksi sudah dibayar.',
        'share_success' => 'Transaksi berhasil dishare.',
        'print_success' => 'Struk siap dicetak.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flash Messages - Sales Returns
    |--------------------------------------------------------------------------
    */
    'sales_returns' => [
        'created' => 'Retur penjualan berhasil dibuat.',
        'updated' => 'Retur penjualan berhasil diperbarui.',
        'completed' => 'Retur penjualan berhasil diselesaikan.',
        'not_found' => 'Retur tidak ditemukan.',
        'qty_exceeded' => 'Jumlah retur melebihi jumlah pembelian.',
        'already_completed' => 'Retur sudah selesai diproses.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flash Messages - Cashier Shifts
    |--------------------------------------------------------------------------
    */
    'cashier_shifts' => [
        'opened' => 'Shift kasir berhasil dibuka.',
        'closed' => 'Shift kasir berhasil ditutup.',
        'force_closed' => 'Shift kasir dipaksa ditutup.',
        'not_found' => 'Shift tidak ditemukan.',
        'already_open' => 'Anda sudah memiliki shift yang aktif.',
        'already_closed' => 'Shift sudah ditutup.',
        'no_active_shift' => 'Tidak ada shift aktif. Buka shift terlebih dahulu.',
        'has_held_carts' => 'Masih ada transaksi ditahan. Selesaikan terlebih dahulu.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flash Messages - Stock Operations
    |--------------------------------------------------------------------------
    */
    'stock' => [
        'opname_created' => 'Stock opname berhasil dibuat.',
        'opname_finalized' => 'Stock opname berhasil di-finalisasi.',
        'opname_updated' => 'Item stock opname berhasil diperbarui.',
        'transfer_created' => 'Transfer stok berhasil dibuat.',
        'transfer_sent' => 'Transfer stok berhasil dikirim.',
        'transfer_received' => 'Transfer stok berhasil diterima.',
        'transfer_cancelled' => 'Transfer stok berhasil dibatalkan.',
        'mutation_logged' => 'Mutasi stok berhasil dicatat.',
        'insufficient_stock' => 'Stok tidak mencukupi untuk operasi ini.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flash Messages - Purchase Orders
    |--------------------------------------------------------------------------
    */
    'purchase_orders' => [
        'created' => 'Purchase Order berhasil dibuat.',
        'placed' => 'Purchase Order berhasil di-order.',
        'cancelled' => 'Purchase Order berhasil dibatalkan.',
        'not_found' => 'Purchase Order tidak ditemukan.',
        'cannot_cancel' => 'PO tidak dapat dibatalkan pada status ini.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flash Messages - Goods Receiving
    |--------------------------------------------------------------------------
    */
    'goods_receivings' => [
        'created' => 'Penerimaan barang berhasil dicatat.',
        'not_found' => 'Penerimaan barang tidak ditemukan.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flash Messages - Supplier Returns
    |--------------------------------------------------------------------------
    */
    'supplier_returns' => [
        'created' => 'Retur supplier berhasil dibuat.',
        'completed' => 'Retur supplier berhasil diselesaikan.',
        'cancelled' => 'Retur supplier berhasil dibatalkan.',
        'not_found' => 'Retur supplier tidak ditemukan.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flash Messages - Receivables
    |--------------------------------------------------------------------------
    */
    'receivables' => [
        'payment_recorded' => 'Pembayaran piutang berhasil dicatat.',
        'partial_payment' => 'Pembayaran sebagian berhasil.',
        'fully_paid' => 'Piutang lunas.',
        'not_found' => 'Piutang tidak ditemukan.',
        'collection_note_updated' => 'Catatan penagihan berhasil diperbarui.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flash Messages - Payables
    |--------------------------------------------------------------------------
    */
    'payables' => [
        'created' => 'Hutang supplier berhasil dicatat.',
        'payment_recorded' => 'Pembayaran hutang berhasil dicatat.',
        'not_found' => 'Hutang tidak ditemukan.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flash Messages - Pricing Rules
    |--------------------------------------------------------------------------
    */
    'pricing_rules' => [
        'created' => 'Aturan harga berhasil dibuat.',
        'updated' => 'Aturan harga berhasil diperbarui.',
        'deleted' => 'Aturan harga berhasil dihapus.',
        'not_found' => 'Aturan harga tidak ditemukan.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flash Messages - Price Lists
    |--------------------------------------------------------------------------
    */
    'price_lists' => [
        'created' => 'Price list berhasil dibuat.',
        'updated' => 'Price list berhasil diperbarui.',
        'deleted' => 'Price list berhasil dihapus.',
        'not_found' => 'Price list tidak ditemukan.',
        'item_added' => 'Item price list berhasil ditambahkan.',
        'item_removed' => 'Item price list berhasil dihapus.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flash Messages - Customer Vouchers
    |--------------------------------------------------------------------------
    */
    'vouchers' => [
        'created' => 'Voucher berhasil dibuat.',
        'updated' => 'Voucher berhasil diperbarui.',
        'deleted' => 'Voucher berhasil dihapus.',
        'redeemed' => 'Voucher berhasil digunakan.',
        'not_found' => 'Voucher tidak ditemukan.',
        'expired' => 'Voucher sudah kadaluarsa.',
        'already_used' => 'Voucher sudah digunakan.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flash Messages - Customer Segments
    |--------------------------------------------------------------------------
    */
    'segments' => [
        'created' => 'Segment berhasil dibuat.',
        'updated' => 'Segment berhasil diperbarui.',
        'deleted' => 'Segment berhasil dihapus.',
        'member_added' => 'Customer berhasil ditambahkan ke segment.',
        'member_removed' => 'Customer berhasil dihapus dari segment.',
        'not_found' => 'Segment tidak ditemukan.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flash Messages - CRM Campaigns
    |--------------------------------------------------------------------------
    */
    'campaigns' => [
        'created' => 'Campaign berhasil dibuat.',
        'updated' => 'Campaign berhasil diperbarui.',
        'deleted' => 'Campaign berhasil dihapus.',
        'processed' => 'Campaign sedang diproses.',
        'cancelled' => 'Campaign berhasil dibatalkan.',
        'mark_sent' => 'Log campaign berhasil ditandai terkirim.',
        'skip' => 'Log campaign berhasil dilewati.',
        'not_found' => 'Campaign tidak ditemukan.',
        'process_failed' => 'Gagal memproses campaign.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flash Messages - Discount Approvals
    |--------------------------------------------------------------------------
    */
    'discount_approvals' => [
        'approved' => 'Diskon berhasil di-approve.',
        'denied' => 'Diskon berhasil ditolak.',
        'not_found' => 'Request tidak ditemukan.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flash Messages - Bank Accounts
    |--------------------------------------------------------------------------
    */
    'bank_accounts' => [
        'created' => 'Rekening bank berhasil ditambahkan.',
        'updated' => 'Rekening bank berhasil diperbarui.',
        'deleted' => 'Rekening bank berhasil dihapus.',
        'toggled' => 'Status rekening berhasil diperbarui.',
        'order_updated' => 'Urutan rekening berhasil diperbarui.',
        'not_found' => 'Rekening bank tidak ditemukan.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flash Messages - Warehouses
    |--------------------------------------------------------------------------
    */
    'warehouses' => [
        'created' => 'Gudang berhasil ditambahkan.',
        'updated' => 'Gudang berhasil diperbarui.',
        'deleted' => 'Gudang berhasil dihapus.',
        'not_found' => 'Gudang tidak ditemukan.',
        'has_transactions' => 'Gudang tidak dapat dihapus karena sudah memiliki transaksi.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flash Messages - Settings
    |--------------------------------------------------------------------------
    */
    'settings' => [
        'saved' => 'Pengaturan berhasil disimpan.',
        'payment_updated' => 'Pengaturan payment berhasil diperbarui.',
        'store_updated' => 'Profil toko berhasil diperbarui.',
        'printer_updated' => 'Pengaturan printer berhasil disimpan.',
        'loyalty_updated' => 'Pengaturan loyalty berhasil disimpan.',
        'target_updated' => 'Target penjualan berhasil diperbarui.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flash Messages - WhatsApp
    |--------------------------------------------------------------------------
    */
    'whatsapp' => [
        'test_sent' => 'Pesan test berhasil dikirim.',
        'test_failed' => 'Gagal mengirim pesan test.',
        'connected' => 'WhatsApp berhasil terhubung.',
        'disconnected' => 'WhatsApp berhasil disconnected.',
        'qr_generated' => 'QR code berhasil di-generate.',
        'start_failed' => 'Gagal memulai WhatsApp service.',
        'not_connected' => 'WhatsApp belum terhubung.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flash Messages - Users & Roles
    |--------------------------------------------------------------------------
    */
    'users' => [
        'created' => 'User berhasil dibuat.',
        'updated' => 'User berhasil diperbarui.',
        'deleted' => 'User berhasil dihapus.',
        'password_changed' => 'Password berhasil diubah.',
        'not_found' => 'User tidak ditemukan.',
        'cannot_delete_self' => 'Anda tidak dapat menghapus akun sendiri.',
    ],

    'roles' => [
        'created' => 'Role berhasil dibuat.',
        'updated' => 'Role berhasil diperbarui.',
        'deleted' => 'Role berhasil dihapus.',
        'not_found' => 'Role tidak ditemukan.',
        'cannot_delete_system' => 'Role sistem tidak dapat dihapus.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flash Messages - Audit Logs
    |--------------------------------------------------------------------------
    */
    'audit_logs' => [
        'access_denied' => 'Anda tidak memiliki izin untuk melihat audit log.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flash Messages - Language
    |--------------------------------------------------------------------------
    */
    'language' => [
        'changed' => 'Bahasa berhasil diubah.',
        'not_found' => 'Bahasa tidak ditemukan.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Messages
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'required' => 'Field :attribute wajib diisi.',
        'email' => 'Format email tidak valid.',
        'min' => 'Field :attribute minimal :min karakter.',
        'max' => 'Field :attribute maksimal :max karakter.',
        'numeric' => 'Field :attribute harus berupa angka.',
        'unique' => 'Field :attribute sudah digunakan.',
        'exists' => 'Field :attribute tidak ditemukan.',
        'date' => 'Format tanggal tidak valid.',
        'integer' => 'Field :attribute harus berupa bilangan bulat.',
        'positive' => 'Field :attribute harus bernilai positif.',
    ],
];
