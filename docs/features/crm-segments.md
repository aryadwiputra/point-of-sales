# CRM & Customer Segments

Kembali ke indeks dokumentasi: `docs/README.md`

## Tujuan

Segmentasi pelanggan, campaign automation, dan reminder untuk meningkatkan engagement dan collection.

## Fitur Saat Ini

### Customer Segments
- Manual: admin menambahkan customer ke segment
- Auto: segment berdasarkan aturan (High Spender, Frequent Buyer, Inactive, Credit Customer, Overdue)
- Rule config: spending threshold, frequency, last purchase, receivables status
- Segment membership track: source (manual/auto), matched_at

### Campaigns
- Buat campaign dengan filter audiens (segmen)
- Process campaign: generate log per customer
- Campaign types: reminder, promo, follow-up
- Share invoice via WhatsApp link
- Cancel campaign jika diperlukan

### Reminders
- Due-soon receivable reminder (3 hari sebelum jatuh tempo)
- Overdue receivable reminder
- Repeat order reminder untuk customer yang sudah lama tidak belanja

## Database

- `customer_segments` — master segment
- `customer_segment_memberships` — pivot customer ↔ segment
- `customer_campaigns` — campaign definition
- `customer_campaign_logs` — per-customer campaign tracking
- `crm_reminders` — reminder definitions

## Halaman dan Route

| Route | Fungsi |
|-------|--------|
| `customer-segments.*` | CRUD segments |
| `crm-campaigns.*` | CRUD campaigns + process/cancel |
| `crm-reminders.index` | Daftar reminders |

## Permission

| Permission | Untuk apa |
|-----------|-----------|
| `customer-segments-access` | Lihat segments |
| `customer-segments-create` | Buat segment |
| `customer-segments-update` | Edit segment |
| `customer-segments-delete` | Hapus segment |
| `crm-campaigns-access` | Lihat campaigns |
| `crm-campaigns-create` | Buat campaign |
| `crm-campaigns-update` | Edit, process, cancel campaign |
| `crm-campaigns-delete` | Hapus campaign |
| `crm-reminders-access` | Lihat reminders |

## Alur Campaign

1. Buat segment (manual/auto)
2. Buat campaign → pilih audiens filter
3. Process campaign → system generate log per customer
4. Manual: mark sent/skip per log
5. Customer menerima notifikasi (via WhatsApp link atau manual follow-up)
