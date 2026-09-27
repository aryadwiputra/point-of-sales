# Security Policy

## Reporting a Vulnerability

Jika Anda menemukan kerentanan keamanan di Point of Sales, **jangan buat issue publik**. Kirim laporan langsung ke:

**Email:** aryadptr.developer@gmail.com

Laporan akan ditanggapi dalam **maksimal 48 jam**. Kami akan merilis patch sesegera mungkin setelah konfirmasi.

## Apa yang Dilaporkan

Kami menerima laporan untuk:
- XSS (Cross-Site Scripting)
- CSRF
- SQL Injection
- Authentication/Authorization bypass
- Sensitive data exposure
- Remote code execution
- Privilege escalation

## Informasi yang Dibutuhkan

Sertakan dalam laporan:
- Versi aplikasi (commit hash atau tag)
- Langkah-langkah untuk mereproduksi
- Dampak potensial
- (Opsional) Saran mitigasi

## Security Practices di Repo Ini

| Area | Praktik |
|------|---------|
| Password | Bcrypt hashing |
| Session | Regenerate after login, absolute lifetime timeout |
| CSRF | Laravel CSRF protection on all routes |
| Auth | Rate limiting, honeypot + timer (bot.guard middleware) |
| RBAC | Spatie Permission + step_up middleware for sensitive actions |
| API tokens | Sanctum; expiring tokens (`SANCTUM_TOKEN_EXPIRATION`), per-module abilities |
| Payment secrets | Encrypted at rest (Xendit/Midtrans keys) |
| Webhook | Constant-time signature verification for Midtrans & Xendit |
| Headers | `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options: DENY`, `Permissions-Policy`. HSTS dikirim hanya di production via HTTPS. CSP dikirim sebagai `Content-Security-Policy-Report-Only` (belum enforce) |
| WhatsApp service | Internal `X-Service-Token` header, binds `127.0.0.1` by default |
| User data | Input validation on all requests |

### Catatan Headers

`SecureHeaders` middleware memasang:
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `X-Frame-Options: DENY`
- `Permissions-Policy` (camera, microphone, geolocation, payment, usb, accelerometer, gyroscope dinonaktifkan)
- `Strict-Transport-Security` — **hanya** saat production + request HTTPS
- `Content-Security-Policy-Report-Only` — **report-only**, belum di-enforce; tujuannya mengumpulkan violation report sebelum enforcement

### API Abilities

Master-data API (`products`, `customers`, `categories`, `warehouses`, `suppliers`) memakai ability per-modul. Endpoint POS (`/api/v1/pos/*`) memerlukan ability `pos-access`. Token hasil login memuat seluruh permission user + `user:read`; token dari registrasi publik hanya `user:read` sehingga tidak bisa mengakses POS.

## Supported Versions

| Version | Supported |
|---------|-----------|
| v2.x | ✅ |
| v1.x | ❌ (legacy) |
