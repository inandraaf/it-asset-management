# 11 — Kebutuhan Non-Fungsional

## 1. Validasi Data

| Kebutuhan | Implementasi |
| --- | --- |
| Format MAC Address benar | Custom rule `MacAddress` (regex + normalisasi uppercase) |
| Blokir duplikasi MAC | `Rule::unique('assets','mac_address')->whereNull('deleted_at')` + partial `UNIQUE` constraint |
| Blokir duplikasi IP | `nullable` + `Rule::unique(...)->whereNull('deleted_at')` + partial `UNIQUE` constraint |
| Blokir duplikasi NIP/Nama Dept | `unique` + constraint |
| Semua validasi via Form Request | Tidak ada `$request->validate()` di controller |
| Pesan error jelas (bahasa Indonesia) | Method `messages()` pada setiap Form Request |

## 2. UI/UX

### Desain Sistem

Antarmuka memakai **layout sidebar admin** yang rapi dan sederhana:

| Elemen | Keterangan |
| --- | --- |
| Layout | Sidebar tetap (fixed) 64 lebar di desktop; overlay + tombol hamburger di mobile |
| Palet | Netral `slate`, aksen `indigo` untuk aksi utama, `rose` untuk destruktif, `emerald`/`amber`/`blue` untuk status |
| Sudut | Kartu & tombol `rounded-xl` / `rounded-lg` |
| Tipografi | Figtree; judul halaman `text-lg font-semibold` |
| Ikon | Heroicons outline (inline SVG) — tanpa dependensi ikon tambahan |
| Mode gelap | **Dimatikan** — tema selalu terang (lihat catatan di bawah) |

### Komponen Reusable

| Komponen | Fungsi |
| --- | --- |
| `<x-card>` | Wadah konten dengan border, sudut membulat, header opsional + slot `actions` |
| `<x-empty-state>` | Tampilan state kosong (ikon + judul + deskripsi + slot `action`) |
| `<x-status-badge :status="$asset->status" />` | Badge status aset dengan titik warna konsisten |
| `<x-assignment-status :active="$assignment->isActive()" />` | Badge status penugasan (Aktif / Selesai) |
| `<x-text-input>`, `<x-input-label>`, `<x-input-error>` | Input form |
| `<x-primary-button>`, `<x-secondary-button>`, `<x-danger-button>` | Tombol aksi |
| `<x-dropdown>` | Menu pengguna di top bar (nama, email, role, profil, keluar) |

> Warna badge status **hanya** didefinisikan di `components/status-badge.blade.php` dan `components/assignment-status.blade.php` — jangan menulis ulang kelasnya di halaman. `AssetStatus::badgeClasses()` sudah dihapus agar tidak ada sumber warna kedua yang menyimpang.

### Prinsip

- **Responsif** (mobile-first). Sidebar menjadi overlay di layar kecil; tabel besar horizontal-scroll.
- **Konsisten**: semua halaman memakai komponen di atas; tidak ada warna `gray-` (memakai `slate-`).
- **Navigasi sadar-role**: menu "Kelola" (Tambah Aset, Aset Terhapus) hanya tampil untuk Admin IT. Menyembunyikan menu **bukan** pengamanan — middleware `role:admin` tetap wajib.
- **Feedback** setiap aksi: flash message sukses (emerald) dan error (rose) di bawah top bar.
- **Konfirmasi hapus** memakai `confirm()` dengan nama dibaca dari atribut `data-*` (bukan diinterpolasi ke string JS — mencegah XSS, lihat [OutputEscapingTest](../tests/Feature/OutputEscapingTest.php)).
- **Alpine + data user**: jangan pernah menaruh output Blade di dalam atribut `x-data`/`x-bind`. `{{ }}` meng-escape `'` menjadi `&#039;`, tetapi HTML parser men-decode-nya kembali **sebelum** Alpine mengevaluasi atribut sebagai JavaScript — sehingga string bisa ditembus (reflected XSS). Selalu pakai `@js(...)`:
  ```blade
  x-data="{ mac: @js(old('mac_address')) }"   {{-- benar --}}
  x-data="{ mac: '{{ old('mac_address') }}' }" {{-- SALAH, rentan XSS --}}
  ```
- **Aksesibilitas**: setiap input punya `<label>`; pesan error terhubung dengan `aria-describedby`; ikon dekoratif diberi `sr-only`; kontras warna memadai.
- **Bahasa**: antarmuka berbahasa Indonesia; istilah teknis (MAC Address, IP Address, Available, Assign, Return, Transfer) tetap bahasa Inggris.
- **State kosong**: pesan informatif + CTA, bukan tabel kosong.
- Timezone tampilan mengikuti `APP_TIMEZONE`; format tanggal `d M Y`.

### Catatan

- Halaman login/guest memakai gaya serupa (kartu membulat, brand ITAM) dan menegaskan bahwa registrasi mandiri dinonaktifkan.
- **Tema sengaja terang saja (tanpa dark mode).** `tailwind.config.js` memakai
  `darkMode: ['class', '.dark']`, dan tidak ada elemen yang diberi class `dark`,
  sehingga varian `dark:` tidak pernah aktif walau OS pengguna memakai dark mode.
  Seluruh kelas `dark:` sudah dihapus dari view. Bila nanti ingin menghidupkan
  dark mode lagi, cukup tambahkan class `dark` pada `<html>` dan tulis ulang
  varian `dark:` yang diperlukan.

## 3. Performa

### Index PostgreSQL (wajib)

| Tabel | Kolom | Index | Alasan |
| --- | --- | --- | --- |
| `assets` | `mac_address` | UNIQUE B-TREE | Lookup & cegah duplikasi |
| `assets` | `ip_address` | UNIQUE B-TREE | Lookup & cegah duplikasi |
| `assets` | `status`, `type` | B-TREE | Filter & statistik dashboard |
| `assets` | `asset_code` | UNIQUE B-TREE | Lookup cepat by kode |
| `employees` | `nip` | UNIQUE | Lookup |
| `employees` | `nama` | B-TREE | Pencarian |
| `asset_assignments` | `(asset_id, returned_date)` | COMPOSITE | Ambil assignment aktif & riwayat |
| `asset_assignments` | `asset_id` WHERE `returned_date IS NULL` | UNIQUE PARTIAL | Satu assignment aktif per aset |
| `asset_assignments` | `(employee_id, returned_date)` | COMPOSITE | Hitung aset aktif per karyawan/departemen (dashboard) |
| `asset_assignments` | `(assigned_date, id)` | COMPOSITE | Daftar "alokasi terbaru" tanpa mengurutkan seluruh riwayat |

Dengan index di atas, pencarian tetap cepat saat data mencapai ribuan baris.

### Pagination

- Wajib page: daftar aset (20/halaman), karyawan (15), departemen (25, atau semua jika sedikit).
- Selalu `->withQueryString()` agar filter/pencarian bertahan saat pindah halaman.

### Hindari N+1

```php
Asset::with(['activeAssignment.employee.department'])->paginate(20);
```

Aktifkan pengecekan di development:

```php
// AppServiceProvider::boot(), hanya di lokal
Model::preventLazyLoading(! app()->isProduction());
```

### Pencarian Teks

- Gunakan `ilike` (PostgreSQL) untuk pencarian tidak case-sensitive.
- Untuk dataset sangat besar (> 100ribu baris), pertimbangkan `pg_trgm` + GIN index:

```sql
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE INDEX assets_brand_trgm_idx ON assets USING gin (brand gin_trgm_ops);
```

### Cache (opsional)

- Statistik dashboard: `Cache::remember('dashboard.stats', 60, ...)`.
- Invalidasi di `AssetObserver` (`saved`, `deleted`) → `Cache::forget('dashboard.stats')`.

### Target Kinerja

| Metrik | Target |
| --- | --- |
| Waktu muat daftar aset (1.000 baris) | < 500 ms |
| Waktu muat dashboard | < 300 ms |
| Jumlah query per halaman daftar | ≤ 10 |
| Pencarian by MAC | < 100 ms (memanfaatkan index) |

## 4. Keamanan

- CSRF protection bawaan Laravel aktif di semua form (`@csrf`).
- Middleware `auth` pada seluruh route kecuali login.
- Middleware `role:admin` pada seluruh route tulis.
- Mass assignment dikendalikan lewat `$fillable` (jangan `$guarded = []`).
- Blade meng-escape output `{{ }}`; jangan gunakan `{!! !!}` untuk data user.
- Password di-hash dengan bcrypt (default Breeze).
- Rate limit login sudah aktif lewat `LoginRequest` bawaan Breeze.
- Registrasi publik dimatikan untuk sistem internal.
- `.env` tidak masuk version control (sudah ada di `.gitignore`).

## 5. Maintenance & Kualitas Kode

| Aspek | Standar |
| --- | --- |
| Formatting | `./vendor/bin/pint` wajib hijau |
| Testing | PHPUnit; minimal 1 feature test per modul |
| Migrasi | Satu migrasi = satu perubahan; jangan edit migrasi yang sudah di-deploy |
| Seeder | `DatabaseSeeder` idempoten (aman dijalankan ulang) |
| Dokumentasi | Perubahan skema → update [03-database.md](03-database.md) |
| Versioning | Semua model transaksional punya `timestamps` |

## 6. Kompatibilitas

| Item | Nilai |
| --- | --- |
| Browser | Chrome, Edge, Firefox versi terbaru (2 versi ke belakang) |
| PHP | 8.1+ |
| PostgreSQL | 13+ |
| Node | 18+ (untuk Vite 5) |
| Layar | Responsif dari 360px hingga desktop |

## 7. Backup & Data

- Backup harian `pg_dump` untuk database produksi.
- Retensi minimal 30 hari.
- Riwayat assignment bersifat append-only dan **tidak boleh hilang** saat karyawan/aset dihapus → gunakan `restrictOnDelete` dan soft delete pada aset.
- Aset yang di-soft delete tetap dapat dipulihkan, sehingga kesalahan hapus tidak berakibat kehilangan data permanen.
- Pemulihan aset wajib divalidasi ulang terhadap konflik MAC/IP dengan aset aktif.
