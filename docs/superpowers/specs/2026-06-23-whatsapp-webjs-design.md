# WhatsApp Gateway via whatsapp-web.js

Integrasi WhatsApp native dengan whatsapp-web.js. User scan QR dari halaman Settings, kirim pesan otomatis dari campaign.

## Arsitektur

```
Laravel App ←→ Node.js Service (whatsapp-service/) ←→ WhatsApp Web (via Puppeteer)
```

- **Node service**: Express di localhost:3001, kelola sesi WA
- **Laravel**: `WhatsAppService.php` wrapper cURL ke Node, dipanggil dari Campaign/Setting
- **React**: Halaman Settings dengan QR scanner + status koneksi

## Node Service (`whatsapp-service/`)

4 endpoint:

| Endpoint | Method | Function |
|----------|--------|----------|
| `/start` | POST | Init/mulai Client WA (re-use session jika ada) |
| `/status` | GET | `{connected, phone, qr}` |
| `/send` | POST | `{target, message}` |
| `/disconnect` | POST | Hapus session + disconnect |

Session persistence via `LocalAuth` ke folder `session/`.

## Backend Laravel

### `WhatsAppService.php`
- `start(): array` — panggil POST /start
- `status(): array` — panggil GET /status
- `send(string $target, string $message): bool` — panggil POST /send
- `disconnect(): bool` — panggil POST /disconnect
- `isAvailable(): bool` — cek `WA_SERVICE_URL` terisi

### `SettingController` — tambah method
- `whatsapp()` — render halaman setting + status device + QR
- `updateWhatsapp()` — save preferensi (auto-send toggle)
- `testWhatsapp()` — test kirim ke nomor owner

### CrmAutomationService
- Di `processCampaign()`: kalau WA available, kirim beneran via Node service
- Update `CustomerCampaignLog` status jadi `sent` setelah sukses
- Fallback ke `wa.me` jika gagal

## Routes
```
GET  /settings/whatsapp                   → whatsapp
POST /settings/whatsapp                   → updateWhatsapp
POST /settings/whatsapp/test              → testWhatsapp
POST /settings/whatsapp/disconnect        → disconnect (panggil Node)
```

## Permission
- `whatsapp-settings-access`
- `whatsapp-settings-update`

## Files
- `whatsapp-service/package.json`
- `whatsapp-service/server.js`
- `whatsapp-service/.gitignore`
- `app/Services/WhatsAppService.php`
- `resources/js/Pages/Dashboard/Settings/Whatsapp.jsx`
- `routes/web.php` (ubah)
- `database/seeders/PermissionSeeder.php` (ubah)
- `app/Services/CrmAutomationService.php` (ubah)
