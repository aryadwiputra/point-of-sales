# App Versioning

Versi aplikasi mengikuti SemVer dengan prefix `v`, misalnya `v2.10.5`.

## Sumber Versi

- Git tag adalah identitas rilis repository.
- `APP_VERSION` adalah nilai runtime yang dibagikan ke frontend.
- `config/app.php` menyediakan fallback saat environment belum mengatur nilai.
- Versi tampil di sidebar dashboard dan navbar POS.

## Release Checklist

1. Tentukan versi SemVer berikutnya.
2. Update `APP_VERSION` di `.env.example` dan environment deployment.
3. Update `CHANGELOG.md` dan halaman roadmap publik.
4. Jalankan test dan production build.
5. Buat annotated Git tag dengan nilai yang sama.
6. Pastikan `APP_VERSION` sama dengan latest Git tag.

Rilis saat ini: `v2.10.5`.
