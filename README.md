# Aplikasi Kasir (Point of Sales) 
Aplikasi ini dapat digunakan untuk melakukan pencatatan transaksi jual beli pada sebuah warung atau toko. Secara template, aplikasi ini menggunakan resource dari https://github.com/Raf-Taufiqurrahman/RILT-Starter dengan beberapa modifikasi yang saya lakukan terhadap komponen-komponen untuk mendukung aplikasi kasir.

## Tech Stack

- Laravel 11.x
- Inertia
- React
- TailwindCSS
- MySQL
## Authors

- [Arya Dwi Putra](https://www.github.com/aryadwiputra)
- [Rafi Taufiqurrahman](https://github.com/Raf-Taufiqurrahman)


## 📌 Fitur

| No  | Nama |  Status                                                     |
|-----|------------------------------------------------------------|------|
|  1  | Authentikasi Admin.                            |Done|
|  2  | Manajemen Pengguna.                 |Done|
|  3  | Manajemen Hak Akses Pengguna.                      | Done|
|  4  | Manajemen Role Pengguna                          |Done|
|  5  | Manajemen Kategori.               |Done|
|  6  | Manajemen Produk.                                    |Done|
|  7  | Manajemen Pelanggan.     |Done|
|  8  | Print Invoice. |Done|
|  9  | Laporan Penjualan. |On progress|
|  10  | Laporan Keuntungan. |On progress|
|  11  | Riwayat Order. |On progress|
|  12  | Chart/Grafik Pendapatan. |On progress|

------------
## 💻 Panduan Instalasi Project

1. **Clone Repository**
```bash
git clone https://github.com/aryadwiputra/point-of-sales 
```
2. **Buka terminal, lalu ketik**
```
cd point-of-sales
composer install
npm install
cp .env.example .env
php artisan key:generate
```

3. **Buka ```.env``` lalu ubah baris berikut sesuaikan dengan databasemu yang ingin dipakai**
```
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=
```

3. **Jalankan bash**
```bash
php artisan config:cache
php artisan storage:link
php artisan route:clear
```

4. **Jalankan migrations dan seeders**
```
php artisan migrate --seed
```
5. **Jalankan nodejs**
```
npm run dev
```

5. **Jalankan website**
```bash
php artisan serve
```

## Jika ada pertanyaan silahkan hubungi saya di email :

```
aryaadwptr@gmail.com
```

## Request Fitur Baru dan Pelaporan Bug

Anda dapat meminta fitur baru maupun melaporkan bug melalui menu **issues** yang sudah disediakan oleh GitHub (lihat menu di atas), posting issues baru dan kita akan berdiskusi disana.

## Berkontribusi

Siapapun dapat berkontribusi pada proyek ini mulai dari pemrograman, pembuakan buku manual, sampai dengan mengenalkan produk ini kepada masyarakat Indonesia agar mengurangi kesenjangan pendidikan teknologi dengan cara membuat postingan issue di repository ini.
