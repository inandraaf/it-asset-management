# Dokumentasi Sistem Manajemen Aset IT (ITAM)

Dokumentasi ini adalah turunan terperinci dari PRD **Sistem Manajemen Aset IT Internal** (Fase MVP). Tujuannya memecah kebutuhan produk menjadi dokumen-dokumen kecil yang bisa langsung dipakai saat development: skema database, aturan validasi, daftar route, dan kriteria penerimaan.

## Ringkasan Proyek

| Item | Nilai |
| --- | --- |
| Nama Proyek | Sistem Manajemen Aset IT (ITAM) Internal |
| Fase | MVP (Minimum Viable Product) |
| Framework | Laravel 10.x |
| Bahasa | PHP 8.1+ |
| Database | PostgreSQL |
| UI | Blade + Tailwind CSS + Alpine.js (Laravel Breeze) |
| Autentikasi | Laravel Breeze |

## Daftar Dokumen

| No | Dokumen | Isi |
| --- | --- | --- |
| 01 | [overview.md](01-overview.md) | Latar belakang, tujuan, personas, ruang lingkup, glosarium |
| 02 | [arsitektur.md](02-arsitektur.md) | Tech stack, struktur folder, konvensi, setup environment |
| 03 | [database.md](03-database.md) | ERD, definisi tabel, kolom, constraint, index, migrasi |
| 04 | [autentikasi.md](04-autentikasi.md) | Login Breeze, peran (role), otorisasi |
| 05 | [master-data.md](05-master-data.md) | CRUD Departemen & Karyawan |
| 06 | [manajemen-aset.md](06-manajemen-aset.md) | CRUD Aset, validasi MAC/IP, pencarian & filter |
| 07 | [alokasi-aset.md](07-alokasi-aset.md) | Assign / Return aset dan riwayat pemakaian |
| 08 | [dashboard.md](08-dashboard.md) | Ringkasan statistik dashboard |
| 09 | [routing-dan-otorisasi.md](09-routing-dan-otorisasi.md) | Daftar route, middleware, policy |
| 10 | [validasi.md](10-validasi.md) | Aturan Form Request lengkap per modul |
| 11 | [non-fungsional.md](11-non-fungsional.md) | UI/UX, performa, index PostgreSQL, keamanan |
| 12 | [acceptance-dan-roadmap.md](12-acceptance-dan-roadmap.md) | Kriteria penerimaan & roadmap milestone |
| 13 | [operasional.md](13-operasional.md) | Setup, akun awal, perintah umum, backup & troubleshooting |

## Cara Memakai Dokumentasi Ini

1. Baca [01-overview.md](01-overview.md) dan [02-arsitektur.md](02-arsitektur.md) sebelum menulis kode.
2. Implementasi migrasi mengikuti [03-database.md](03-database.md).
3. Setiap modul (05–08) berisi daftar file yang harus dibuat, route, dan aturan bisnisnya.
4. Gunakan [10-validasi.md](10-validasi.md) sebagai acuan tunggal aturan validasi agar tidak terjadi duplikasi logika.
5. Cek [12-acceptance-dan-roadmap.md](12-acceptance-dan-roadmap.md) untuk menentukan status MVP.

## Status

- [x] Skema database & migrasi
- [x] Autentikasi & role
- [x] CRUD Departemen
- [x] CRUD Karyawan
- [x] CRUD Aset
- [x] Assign / Return / Transfer aset
- [x] Riwayat aset
- [x] Soft delete & restore aset
- [x] Dashboard
- [x] Uji kriteria penerimaan

## Status MVP: SELESAI

Seluruh kriteria penerimaan (AC-1 s/d AC-4) terverifikasi. Lihat [12-acceptance-dan-roadmap.md](12-acceptance-dan-roadmap.md)
untuk detail per milestone, dan [13-operasional.md](13-operasional.md) untuk cara menjalankan.

## Keputusan Desain yang Sudah Disetujui

| Topik | Keputusan | Dokumen |
| --- | --- | --- |
| Hapus aset | Soft delete (`deleted_at`) + partial unique index + halaman restore | [03](03-database.md), [06](06-manajemen-aset.md) |
| Perubahan status saat `Assigned` | Tidak boleh manual ke `Available`; harus via Return | [10](10-validasi.md) |
| Transfer aset | Satu form/klik, dua baris riwayat (Return + Assign dalam satu transaksi) | [07](07-alokasi-aset.md) |
| Default role akun baru | `viewer` (least privilege); `role` tidak mass-assignable | [03](03-database.md), [04](04-autentikasi.md) |
| Registrasi publik | Dimatikan; akun dibuat Admin IT | [04](04-autentikasi.md) |
| Password seeder | Dari `SEED_ADMIN_PASSWORD`, bukan hardcode | [04](04-autentikasi.md) |
| Tipe enum di DB | `varchar` + `CHECK constraint`, bukan PostgreSQL `ENUM type` | [03](03-database.md) |
| Database test | Terpisah (`inven_it_testing`) via `phpunit.xml`, memakai PostgreSQL | [02](02-arsitektur.md) |
| Password akun viewer | Terpisah (`SEED_VIEWER_PASSWORD`); di production akun demo tidak dibuat bila kosong | [04](04-autentikasi.md) |
| Layout UI | Sidebar tetap + top bar, komponen `<x-card>`/`<x-empty-state>`/badge | [11](11-non-fungsional.md) |
| Data user di atribut Alpine | Wajib `@js()`, dilarang `{{ }}` di dalam `x-data` (XSS) | [11](11-non-fungsional.md) |
| Warna badge status | Hanya di komponen `status-badge`/`assignment-status` | [11](11-non-fungsional.md) |
| Kode aset | Selalu dibuat sistem, form tidak menerima input kode | [06](06-manajemen-aset.md) |
| Komponen komputer | Disimpan sebagai key di `specs` (jsonb), bukan tabel/aset terpisah | [06](06-manajemen-aset.md) §11 |
| Hostname komputer | Kolom tersendiri (bukan bagian `specs`), unik antar aset aktif | [03](03-database.md), [06](06-manajemen-aset.md) |
