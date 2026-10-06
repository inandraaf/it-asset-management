# 02 — Arsitektur & Konvensi

## 1. Tech Stack

| Layer | Teknologi | Catatan |
| --- | --- | --- |
| Framework | Laravel 10.x | `composer.json` sudah memuat `laravel/framework ^10.10` |
| Bahasa | PHP 8.1+ | Gunakan typed properties & return types |
| Database | PostgreSQL | `DB_CONNECTION=pgsql`, driver `pgsql` — lihat §6b untuk konfigurasi Docker |
| Auth | Laravel Breeze | Sudah terpasang (`laravel/breeze ^1.29`) |
| CSS | Tailwind CSS 3 + `@tailwindcss/forms` | `tailwind.config.js` |
| Interaktivitas | Alpine.js | Bawaan Breeze |
| Build | Vite 5 | `npm run dev` / `npm run build` |
| Testing | PHPUnit 10 | `php artisan test` |
| Runtime | Docker (`php81`) | **Semua perintah via `docker exec`** — lihat §6b |

> Catatan: `.env` sudah memakai `DB_CONNECTION=pgsql` (nilai standar Laravel, key `pgsql` ada di `config/database.php`). Konfigurasi host database bergantung pada environment Docker — lihat §6b.

## 2. Peran (Role) — Kolom `role` pada `users`

| Role | Nilai | Akses |
| --- | --- | --- |
| Admin IT | `admin` | CRUD penuh |
| Viewer | `viewer` | Read-only |

Implementasi MVP: gunakan enum sederhana (`string` + `check` constraint) dan helper `User::isAdmin()`. Detail di [09-routing-dan-otorisasi.md](09-routing-dan-otorisasi.md).

## 3. Struktur Folder Target

```
app/
├── Enums/
│   ├── AssetStatus.php
│   ├── AssetType.php
│   └── UserRole.php
├── Http/
│   ├── Controllers/
│   │   ├── AssetController.php
│   │   ├── AssetAssignmentController.php
│   │   ├── DashboardController.php
│   │   ├── DepartmentController.php
│   │   └── EmployeeController.php
│   ├── Middleware/
│   │   └── EnsureUserHasRole.php
│   └── Requests/
│       ├── Asset/
│       │   ├── StoreAssetRequest.php
│       │   └── UpdateAssetRequest.php
│       ├── AssignAssetRequest.php
│       ├── ReturnAssetRequest.php
│       ├── TransferAssetRequest.php
│       ├── DepartmentRequest.php
│       └── EmployeeRequest.php
├── Models/
│   ├── Asset.php
│   ├── AssetAssignment.php
│   ├── Department.php
│   └── Employee.php
├── Policies/
│   └── AssetPolicy.php
└── Services/
    ├── AssetAllocationService.php
    └── AssetCodeGenerator.php

config/
└── itam.php                       # SEED_ADMIN_PASSWORD dan config khusus ITAM

database/
├── migrations/
├── factories/
└── seeders/

resources/views/
├── assets/
│   ├── index.blade.php
│   ├── create.blade.php
│   ├── edit.blade.php
│   ├── show.blade.php
│   ├── assign.blade.php
│   ├── transfer.blade.php
│   └── trashed.blade.php
├── departments/
├── employees/
├── components/
│   ├── card.blade.php            # wadah konten (border, rounded, header opsional)
│   ├── empty-state.blade.php     # tampilan state kosong
│   ├── status-badge.blade.php    # badge status aset
│   ├── assignment-status.blade.php # badge status penugasan (Aktif/Selesai)
│   └── ...                       # tombol, input, dropdown (Breeze + kustom)
├── layouts/
│   ├── app.blade.php             # shell sidebar + top bar
│   ├── navigation.blade.php      # sidebar (sadar-role)
│   └── guest.blade.php           # layout login
└── dashboard.blade.php
```

> Komponen Breeze `nav-link`, `responsive-nav-link`, dan `application-logo`
> dihapus karena digantikan layout sidebar dan logo brand di
> `public/images/logo.png`.

## 4. Konvensi Kode

- **Model**: singular PascalCase (`Asset`), tabel plural snake_case (`assets`).
- **Foreign key**: `<singular>_id`, contoh `department_id`, `asset_id`.
- **Primary key**: `id` (bigint auto increment) sesuai default Laravel.
- **Route**: resourceful controller, nama route berbahasa Inggris (`assets.index`, `employees.store`).
- **Controller**: thin controller; logika lintas-tabel (assign/return/transfer) masuk ke `Services`.
- **Validasi**: selalu lewat Form Request, bukan `$request->validate()` inline.
- **Enum**: gunakan PHP 8.1 backed enum + cast model.
- **Soft delete**: hanya untuk `assets`; tidak untuk tabel log (`asset_assignments`).
- **Timezone**: simpan `timestamps` dalam UTC; tampilkan sesuai `APP_TIMEZONE`.
- **Format tanggal tampilan**: `d M Y` (contoh: `05 Oct 2026`).

## 5. Enum PHP (Target)

```php
// app/Enums/AssetType.php
enum AssetType: string
{
    case PC = 'PC';
    case Laptop = 'Laptop';
}

// app/Enums/AssetStatus.php
enum AssetStatus: string
{
    case Available = 'Available';
    case Assigned = 'Assigned';
    case InRepair = 'In Repair';
    case Retired = 'Retired';
}

// app/Enums/UserRole.php
enum UserRole: string
{
    case Admin = 'admin';
    case Viewer = 'viewer';
}
```

> Nilai string enum **harus persis** sama dengan nilai di `check` constraint PostgreSQL pada [03-database.md](03-database.md).

## 6. Setup Lokal

```bash
# 1. Dependency
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate
# pastikan DB_CONNECTION=pgsql, DB_HOST=postgres16, DB_DATABASE=inven_it

# 3. Database
php artisan migrate --seed

# 4. Jalankan
npm run dev
php artisan serve
```

## 6b. Environment Docker (WAJIB DIBACA)

Proyek ini **tidak dijalankan langsung di host**. Development lokal memakai Docker dengan container:

| Container | Image | Host Port | Fungsi |
| --- | --- | --- | --- |
| `php81` | `docker-setup-php81` | **8081** → 80 | PHP 8.1.34, menjalankan aplikasi |
| `postgres16` | `postgres:16-alpine` | 5432 | Database server |
| `adminer_global` | `adminer` | 8080 | GUI database (opsional) |

Kedua container berada di network `docker-setup_default`, sehingga **nama container langsung resolve sebagai hostname**.

### Aturan menjalankan perintah

Semua perintah artisan/composer/php/npm **harus lewat `docker exec`**, bukan dijalankan dari host:

```bash
# Benar
docker exec php81 sh -c 'cd /var/www/html/inven_it && php artisan migrate'

# Salah — host tidak punya postgres16 di DNS-nya
php artisan migrate
```

Working directory aplikasi di dalam container: `/var/www/html/inven_it`.

### Konfigurasi `.env`

| Key | Nilai | Alasan |
| --- | --- | --- |
| `DB_CONNECTION` | `pgsql` | Driver PostgreSQL standar |
| `DB_HOST` | `postgres16` | Nama container; `127.0.0.1` akan gagal karena menunjuk ke container `php81` sendiri |
| `DB_PORT` | `5432` | Port internal container, bukan port host |
| `DB_DATABASE` | `inven_it` | Sudah dibuat di `postgres16` |

> `host.docker.internal` juga terbukti berfungsi dan bersifat lebih portable bila aplikasi keluar dari network Docker yang sama. Yang dipilih untuk proyek ini adalah `postgres16` karena lebih eksplisit. Jangan gunakan `127.0.0.1` — itu penyebab error `SQLSTATE[08006] Connection refused`.

### Akses dari luar

| Layanan | URL |
| --- | --- |
| Aplikasi web | `http://localhost:8081/inven_it/public/` |
| Adminer | `http://localhost:8080` |

> **Penting — base path.** Container `php81` memuat seluruh folder `php/81` ke
> `/var/www/html`, dan Apache dikonfigurasi dengan auto-routing. Karena Laravel
> punya folder `public/`, aplikasi **harus** diakses lewat
> `http://localhost:8081/inven_it/public/`.
>
> | URL | Hasil |
> | --- | --- |
> | `/inven_it/public/` | ✅ Bekerja — masuk ke `dashboard` (atau `login` bila belum masuk) |
> | `/inven_it/` | ❌ 404 (auto-routing gagal karena `inven_it/index.php` terdeteksi) |
>
> Konsekuensinya: **selalu gunakan helper route** (`route()`, `redirect()->route()`)
> di kode, jangan menulis path literal seperti `Route::redirect('/', '/dashboard')`,
> karena path literal akan kehilangan prefix `/inven_it/public`.

### Verifikasi koneksi database

```bash
docker exec php81 sh -c 'cd /var/www/html/inven_it && php artisan migrate:status'
```

Bila muncul `Migration table not found.` (bukan error koneksi), berarti koneksi ke PostgreSQL **sudah benar** dan database masih kosong — siap untuk `migrate`.

Cek database dan driver yang sedang aktif:

```bash
docker exec php81 sh -c 'cd /var/www/html/inven_it && php artisan tinker --execute="echo DB::connection()->getDatabaseName();"'
```

### Otoritas file

File yang dibuat agent di host akan dimiliki user host, sementara proses container berjalan sebagai user lain. Jika muncul error permission saat `migrate` atau menulis log, jalankan:

```bash
docker exec php81 sh -c 'cd /var/www/html/inven_it && chmod -R 775 storage bootstrap/cache'
```

## 7. Perintah Berguna

Semua perintah di bawah diasumsikan dijalankan sebagai:

```bash
docker exec php81 sh -c 'cd /var/www/html/inven_it && <perintah>'
```

| Perintah | Fungsi |
| --- | --- |
| `php artisan migrate:fresh --seed` | Reset database + data awal |
| `php artisan migrate:status` | Cek status migrasi & koneksi DB |
| `php artisan test` | Jalankan seluruh test |
| `php artisan test --filter=AssetTest` | Test spesifik |
| `./vendor/bin/pint` | Format kode PHP |
| `npm run build` | Build asset produksi |
| `php artisan config:clear` | Bersihkan cache config setelah ubah `.env` |

## 8. Definition of Done (per fitur)

1. Migrasi & model dengan relasi dan cast lengkap.
2. Form Request dengan aturan dari [10-validasi.md](10-validasi.md).
3. Controller + route terdaftar dengan middleware role.
4. View Blade responsif (mobile-first) memakai komponen Breeze.
5. Minimal 1 feature test untuk alur bahagia dan 1 untuk kasus validasi gagal.
6. `php artisan test` hijau dan `./vendor/bin/pint` bersih.

## 9. Database untuk Testing

Test memakai database **terpisah** dari development agar `RefreshDatabase`
tidak menghapus data kerja kita.

| Database | Dipakai oleh | Catatan |
| --- | --- | --- |
| `inven_it` | Aplikasi (`docker-compose` / `.env`) | Data development |
| `inven_it_testing` | `php artisan test` (diatur di `phpunit.xml`) | **Selalu dikosongkan** setiap test run |

Membuat database test (sekali saja):

```bash
docker exec postgres16 psql -U postgres -c "CREATE DATABASE inven_it_testing"
```

Konfigurasi di `phpunit.xml` sengaja memakai **PostgreSQL, bukan SQLite
in-memory**, karena fitur yang diuji bergantung pada perilaku khas PostgreSQL:

- partial unique index (`WHERE deleted_at IS NULL`)
- kolom `jsonb` untuk `specs`
- operator `ilike` untuk pencarian tanpa memandang huruf besar/kecil

Dengan SQLite, test bisa hijau padahal constraint tidak benar-benar bekerja.

> **Peringatan:** jangan pernah mengarahkan `DB_DATABASE` pada `phpunit.xml`
> ke `inven_it`. Satu kali salah arah, seluruh data development terhapus.
