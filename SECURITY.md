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
| Payment secrets | Encrypted at rest (Xendit/Midtrans keys) |
| Webhook | Signature verification for Midtrans & Xendit |
| Headers | SecureHeaders middleware (CSP, HSTS, X-Frame-Options) |
| User data | Input validation on all requests |

## Supported Versions

| Version | Supported |
|---------|-----------|
| v2.x | ✅ |
| v1.x | ❌ (legacy) |
