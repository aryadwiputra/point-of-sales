# API & Integrasi

API v1 menyediakan akses token-based untuk aplikasi mobile, perangkat kasir, dan integrasi eksternal.

## Authentication

- Login dan register tersedia di `/api/v1/auth/*`.
- Endpoint terlindungi memakai Sanctum Bearer token.
- Token login membawa abilities dari permission user.
- Endpoint master data memeriksa ability yang sesuai.

## Endpoint Utama

- Master data: products, customers, categories, warehouses, suppliers.
- POS: shift, product scan, cart, hold/resume, checkout, transactions.
- Offline sync: `POST /api/v1/pos/transactions/sync`.
- Payment webhooks: `/api/webhooks/midtrans` dan `/api/webhooks/xendit`.

## Dokumentasi API

Pada instalasi aktif, dokumentasi Scramble tersedia di `/docs/api` dan OpenAPI JSON di `/docs/api.json`.
Akses dokumentasi produksi dapat dilindungi dengan `SCRAMBLE_DOCS_TOKEN`.
