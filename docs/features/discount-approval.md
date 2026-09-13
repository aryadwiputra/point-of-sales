# Discount Approval

Discount approval mengontrol diskon yang melewati batas kebijakan sebelum checkout diselesaikan.

## Alur

1. Kasir membuat permintaan diskon pada transaksi.
2. Sistem menyimpan status `pending` dan detail nilai diskon.
3. User dengan permission approval meninjau permintaan.
4. Permintaan dapat `approved` atau `rejected` dengan catatan.
5. Checkout hanya memakai diskon yang sudah disetujui.

## Audit dan Permission

Approval menyimpan perubahan status dan aktor pada audit log. Akses kasir dan approver dipisahkan melalui permission discount approval.

## File Sentral

- `app/Http/Controllers/Apps/DiscountApprovalController.php`
- `app/Models/DiscountApprovalLog.php`
- `docs/features/pos-transactions.md`
