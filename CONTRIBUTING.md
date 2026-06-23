# Contributing to Point of Sales

Terima kasih sudah tertarik untuk berkontribusi! 🎉

## Git Workflow

Repo ini menggunakan **Git Flow** dengan branch sebagai berikut:

| Branch | Fungsi |
|--------|--------|
| `main` | Production. Hanya diisi dari PR `development` |
| `development` | Integrasi. Feature branch merge via PR |
| `feature/*` | Kerja fitur. Branch dari `development`, PR ke `development` |
| `fix/*` | Hotfix. Branch dari `main`, PR ke `main` + `development` |
| `release/*` | Release candidate. Dari `development`, merge ke `main` |

## Cara Berkontribusi

### 1. Clone & Setup

```bash
git clone https://github.com/aryadwiputra/point-of-sales.git
cd point-of-sales
cp .env.example .env
composer install && npm install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm run dev
php artisan serve
```

### 2. Buat Branch Fitur

```bash
git checkout development
git checkout -b feature/nama-fitur-anda
```

### 3. Kerja & Commit

Gunakan **Conventional Commits**:

```
feat: tambah fitur X
fix: perbaiki bug Y
docs: update dokumentasi Z
chore: update dependency
refactor: refactor fungsi A
test: tambah test untuk B
```

### 4. Sebelum Pull Request

Pastikan semua lulus:

```bash
vendor/bin/pint                    # PHP formatter
php artisan test                   # All tests pass
npm run build                      # Production build OK
```

### 5. Pull Request

1. Push branch ke GitHub
2. Buat PR ke branch `development`
3. Deskripsikan perubahan:
   - **Apa yang diubah**
   - **Kenapa diubah**
   - **Cara testing**
4. Pastikan PR title mengikuti conventional commits

### 6. Setelah PR

- PR akan direview oleh maintainer
- Jika ada perubahan yang diminta, push ke branch yang sama
- Setelah approved, maintainer akan merge

## Development Tips

- Set `tax_rate=0` pada `Product::create` di test untuk menghindari perubahan grand_total
- Jalankan `php artisan migrate` jika modul baru belum punya tabel
- Baca `AGENTS.md` untuk informasi developer commands
- Lihat `docs/` untuk dokumentasi fitur

## Reporting Bugs

Buka issue baru di GitHub dengan template Bug Report.

## Feature Request

Buka issue baru di GitHub dengan template Feature Request.
