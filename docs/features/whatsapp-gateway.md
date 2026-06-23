# WhatsApp Gateway

Kirim pesan WhatsApp otomatis via campaign CRM, reminder piutang, dan invoice share.

## Cara Kerja

```
Laravel App  ──HTTP──>  whatsapp-service (Node.js)  ──>  WhatsApp Web
                              │
                        Puppeteer (headless Chrome)
```

- `whatsapp-service/` adalah Express server port 3001 yang menjalankan `whatsapp-web.js`
- Session disimpan di folder `session/` (persistent — restart server tidak perlu scan ulang)
- Laravel memanggil Node service via HTTP (cURL) dari `WhatsAppService.php`

## Setup

```bash
cd whatsapp-service
npm install
npm start
# → Running on port 3001
```

## Halaman Settings

`Dashboard > Pengaturan > WhatsApp`

| Fitur | Fungsi |
|-------|--------|
| URL Service | Alamat Node service (default: `http://localhost:3001`) |
| Hubungkan | Init Client WA, generate QR code |
| QR Scan | Scan dengan WhatsApp > Perangkat Tertaut > Perangkat Baru |
| Putuskan | Hapus session + disconnect |
| Kirim Otomatis | Auto-kirim reminder piutang / invoice via campaign |
| Test Kirim | Kirim pesan test ke nomor tertentu |

## Endpoint Node Service

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/start` | POST | Init Client WA, mulai QR generation |
| `/status` | GET | `{connected, phone, qr, starting}` |
| `/send` | POST | Kirim pesan `{target, message}` |
| `/disconnect` | POST | Hapus session + disconnect |

## Integrasi Campaign

Di `CrmAutomationService@processCampaign()`:
- Jika gateway aktif (`wa_enabled=true`) + terhubung → kirim WA beneran ke setiap customer
- Update log status jadi `sent` setelah sukses
- Fallback ke `wa.me` link jika gateway mati

## Permission

- `whatsapp-settings-access` — lihat halaman settings
- `whatsapp-settings-update` — ubah konfigurasi + connect/disconnect

## Catatan Teknis

- Membutuhkan Chrome/Chromium di server (Puppeteer internal)
- Session persistent via `LocalAuth` — tidak perlu scan ulang tiap restart
- Rate limit: WhatsApp Web punya batas pengiriman, hindari >100 pesan berturut-turut
- ToS: whatsapp-web.js menggunakan WhatsApp Web secara tidak resmi
- Deploy production: jalankan dengan PM2: `pm2 start whatsapp-service/server.js --name wa-service`
