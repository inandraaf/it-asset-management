# 13 — Panduan Operasional

Panduan singkat untuk menjalankan, merawat, dan mem-backup sistem.

## 1. Lingkungan

Proyek berjalan di Docker, **bukan** langsung di host.

| Container | Fungsi | Port host |
| --- | --- | --- |
| `php81` | PHP 8.1 + Apache (aplikasi) | 8081 → 80 |
| `postgres16` | Database PostgreSQL 16 | 5432 |
| `adminer_global` | GUI database | 8080 |

Working directory aplikasi di dalam container: `/var/www/html/inven_it`.

**URL aplikasi:** `http://localhost:8081/inven_it/public/`

> Semua perintah `php artisan`, `composer`, dan `npm` **wajib** dijalankan lewat `docker exec`.

## 2. Setup Awal

```bash
# 1. Dependency (sekali saja / saat berubah)
docker exec php81 sh -c 'cd /var/www/html/inven_it && composer install'
docker exec php81 sh -c 'cd /var/www/html/inven_it && npm install'

# 2. Environment
docker exec php81 sh -c 'cd /var/www/html/inven_it && cp .env.example .env'
docker exec php81 sh -c 'cd /var/www/html/inven_it && php artisan key:generate'
# Sesuaikan DB_HOST=postgres16, DB_DATABASE=inven_it, dan SEED_ADMIN_PASSWORD

# 3. Migrasi + data awal
docker exec php81 sh -c 'cd /var/www/html/inven_it && php artisan migrate --seed'

# 4. Asset frontend (produksi)
docker exec php81 sh -c 'cd /var/www/html/inven_it && npm run build'
```

> **PENTING — nilai `.env` yang mengandung spasi wajib dikutip.**
> Dotenv gagal mem-parse nilai berspasi tanpa tanda kutip, dan aplikasi
> langsung mengembalikan **HTTP 500** dengan pesan
> `Failed to parse dotenv file. Encountered unexpected whitespace`.
>
> ```dotenv
> APP_NAME=IT Asset Management      # SALAH -> error 500
> APP_NAME="IT Asset Management"    # BENAR
> ```
>
> Cara memeriksa cepat:
>
> ```bash
> docker exec php81 sh -c 'cd /var/www/html/inven_it && php artisan about'
> ```
>
> Bila `.env` tidak valid, perintah ini langsung melaporkan baris bermasalah.

### Membuat database test (sekali saja)

```bash
docker exec postgres16 psql -U postgres -c "CREATE DATABASE inven_it_testing"
```

## 3. Akun Awal

Dibuat oleh `UserSeeder`. Password diambil dari `SEED_ADMIN_PASSWORD` (lihat [04-autentikasi.md](04-autentikasi.md) §6).

| Username | Role | Akses |
| --- | --- | --- |
| `admin` | Admin IT | Penuh (CRUD + serahkan/tarik/pindah) |
| `viewer` | Viewer | Baca saja |

> Registrasi publik **dimatikan**. Akun baru hanya dibuat oleh Admin IT.
> Di environment non-lokal, `SEED_ADMIN_PASSWORD` wajib diisi; bila kosong seeder berhenti dengan error.
>
> **Password viewer terpisah.** `viewer@example.com` memakai `SEED_VIEWER_PASSWORD`. Bila env itu kosong:
> - di **lokal**, viewer memakai password admin (agar mudah login);
> - di **production**, akun viewer demo **tidak dibuat** sama sekali.
>> Jadi di production, kedua akun tidak pernah berbagi password yang sama.

## 3b. Data Contoh (Seeder)

`DatabaseSeeder` menjalankan seeder berikut secara berurutan:

| Seeder | Isi |
| --- | --- |
| `DepartmentSeeder` | 5 departemen: EDP, HRGA, EP, RnD, CC |
| `UserSeeder` | Akun admin & viewer (lihat §3) |
| `EmployeeSeeder` | **15 karyawan** tersebar di 5 departemen (NIP 2021xxxx–2025xxxx) |
| `AssetSeeder` | **16 aset** (10 PC + 6 laptop) dengan hostname, OS, dan penugasan |
| `ComponentSeeder` | **24 komponen** (RAM, disk, GPU, monitor, dst.) + **21 pemasangan** contoh ke host |

### Isi `AssetSeeder`

- **Komponen** dikelola sebagai aset tersendiri (tabel `components`); lihat [14-manajemen-komponen.md](14-manajemen-komponen.md).
- **OS** disimpan di `assets.specs`; part fisik dipasang ke host lewat `component_installations`.
- **Hostname** mengikuti pola per departemen, contoh `pc-rnd-01`, `lt-edp-01`.
- **Status bervariasi**: 10 terpakai, 3 menganggur, 2 in repair, 1 retired.
- **Riwayat transfer**: `pc-edp-01` sengaja punya riwayat dua baris (Siti Aminah → Budi Santoso) agar halaman riwayat aset tidak kosong saat didemokan.
- **Kode aset** dibuat lewat `AssetCodeGenerator` (bukan ditulis manual), sehingga formatnya sama dengan hasil pemakaian nyata.

### Idempotensi

Semua seeder **aman dijalankan ulang**:

| Seeder | Kunci alami |
| --- | --- |
| Departemen | `nama_dept` |
| Karyawan | `nip` |
| Aset | `mac_address` (kode aset yang sudah ada tidak berubah) |
| Riwayat | tidak digandakan bila sudah ada |

```bash
# Menjalankan ulang seeder tanpa menghapus data
docker exec php81 sh -c 'cd /var/www/html/inven_it && php artisan db:seed'
```

> **Catatan produksi.** Data contoh ini untuk development/demo. Untuk environment
> nyata, jalankan hanya `DepartmentSeeder` dan `UserSeeder`:
>
> ```bash
> docker exec php81 sh -c 'cd /var/www/html/inven_it && php artisan db:seed --class=DepartmentSeeder --force && php artisan db:seed --class=UserSeeder --force'
> ```

## 4. Perintah Umum

Semua contoh memakai prefix:

```bash
docker exec php81 sh -c 'cd /var/www/html/inven_it && <perintah>'
```

| Kebutuhan | Perintah |
| --- | --- |
| Cek koneksi & status migrasi | `php artisan migrate:status` |
| Reset database + seed ulang | `php artisan migrate:fresh --seed` |
| Jalankan seluruh test | `php artisan test` |
| Test satu file | `php artisan test --filter=AssetTest` |
| Format kode | `./vendor/bin/pint` |
| Cek format tanpa mengubah | `./vendor/bin/pint --test` |
| Bersihkan cache config | `php artisan config:clear` |
| Daftar route + middleware | `php artisan route:list` |
| Build asset produksi | `npm run build` |
| Perbaiki permission | `chmod -R 775 storage bootstrap/cache` |
| Reset kata sandi akun | `php artisan user:reset-password {username}` |

### Contoh lengkap

```bash
# Reset database development
docker exec php81 sh -c 'cd /var/www/html/inven_it && php artisan migrate:fresh --seed'

# Jalankan test
docker exec php81 sh -c 'cd /var/www/html/inven_it && php artisan test'

# Format kode
docker exec php81 sh -c 'cd /var/www/html/inven_it && ./vendor/bin/pint'
```

> **PENTING — `config:cache` vs test.** Setelah `php artisan config:cache`, file
> `phpunit.xml` tidak lagi bisa mengarahkan test ke database `inven_it_testing`
> (konfigurasi ter-cache menang). Akibatnya `RefreshDatabase` akan menghapus
> database **development**. Karena itu:
> - Selalu `php artisan config:clear` sebelum menjalankan test.
> - Sebagai jaring pengaman, `tests/TestCase.php` menolak berjalan bila nama
>   database tidak berakhiran `_testing`, sehingga data development tidak akan
>   terhapus tanpa sengaja.

## 5b. APP_KEY — Wajib Di-backup Terpisah

> **Berlaku setelah [FB-8](15-feedback-dan-tindak-lanjut.md) dikerjakan** (kredensial Windows
> & VNC disimpan terenkripsi).

Laravel mengenkripsi data sensitif memakai `APP_KEY`. Konsekuensinya:

| Kejadian | Akibat |
| --- | --- |
| `APP_KEY` hilang / berubah | Seluruh kredensial tersimpan **tidak bisa dibuka lagi** |
| Backup database **tanpa** `APP_KEY` | Data kredensial ikut ter-backup tetapi tidak berguna |

**Aturan:**

1. **Backup `APP_KEY` secara terpisah** dan aman — jangan hanya mengandalkan `.env` di server.
2. Jangan pernah mengganti `APP_KEY` pada sistem yang sudah berjalan.
3. Uji restore **database + `APP_KEY` bersamaan**; restore DB saja tidak cukup.
4. Batasi akses file `.env` hanya untuk operator yang berwenang.

## 5. Backup & Restore Database

### Backup (dump)

```bash
# Simpan ke file di dalam container, lalu copy ke host
docker exec postgres16 pg_dump -U postgres -d inven_it > backup_inven_it_$(date +%Y%m%d_%H%M%S).sql
```

### Restore

```bash
docker exec -i postgres16 psql -U postgres -d inven_it < backup_inven_it_YYYYMMDD_HHMMSS.sql
```

### Rekomendasi Operasional

| Aspek | Rekomendasi |
| --- | --- |
| Frekuensi backup | Harian (mis. cron 02:00) |
| Retensi | Minimal 30 hari |
| Lokasi | Di luar container (host / object storage) |
| Uji restore | Lakukan minimal sekali sebelum go-live |
| Riwayat alokasi | Bersifat append-only — jangan hapus baris `asset_assignments` secara manual |

> **Penting:** hapus permanen aset (`force-delete`) bersifat destruktif terhadap riwayat.
> Sistem sudah menolaknya bila aset memiliki riwayat assignment. Untuk aset yang
> ingin "dipensiunkan", ubah status menjadi `Retired`, jangan dihapus.

## 6. Checklist Rilis (Go-Live)

- [ ] `.env` produksi: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` benar.
- [ ] `SEED_ADMIN_PASSWORD` diisi kuat dan **tidak** di-commit.
- [ ] `SEED_VIEWER_PASSWORD` diisi atau dibiarkan kosong (akun viewer demo tidak dibuat).
- [ ] Password akun `admin@example.com` diganti setelah login pertama.
- [ ] Bila akun viewer tetap dibuat, passwordnya **berbeda** dari admin.
- [ ] `php artisan migrate --force` dijalankan.
- [ ] `npm run build` dijalankan (bukan `npm run dev`).
- [ ] `php artisan config:cache` dan `route:cache` (opsional, untuk performa).
- [ ] **`config:clear` sebelum menjalankan test lokal** (lihat peringatan §4).
- [ ] Backup otomatis terjadwal dan sudah diuji restore.
- [ ] Akses database (port 5432) dan Adminer (8080) **tidak** diekspos ke publik.
- [ ] Seluruh test hijau: `php artisan test`.

## 7. Troubleshooting

| Gejala | Penyebab umum | Solusi |
| --- | --- | --- |
| `SQLSTATE[08006] Connection refused` | `DB_HOST` salah | Set `DB_HOST=postgres16`; jangan `127.0.0.1` |
| Halaman 404 di `/inven_it/` | Salah base path | Gunakan `/inven_it/public/` |
| Perubahan `.env` tidak terbaca | Config ter-cache | `php artisan config:clear` |
| Error permission saat migrate | Ownership file | `chmod -R 775 storage bootstrap/cache` |
| Test menghapus data kerja | `phpunit.xml` salah arah | Pastikan `DB_DATABASE=inven_it_testing` |
| Test gagal: "Menolak menjalankan test pada database non-test" | `config:cache` aktif | Jalankan `php artisan config:clear` lalu ulangi test |
| `Migration table not found.` | Database kosong (koneksi OK) | Jalankan `php artisan migrate --seed` |
| **HTTP 500** setelah mengubah `.env` | Nilai mengandung spasi tanpa tanda kutip | Bungkus dengan tanda kutip: `APP_NAME="IT Asset Management"`, lalu `php artisan config:clear` |
