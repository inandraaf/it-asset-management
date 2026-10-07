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
| 14 | [manajemen-komponen.md](14-manajemen-komponen.md) | **Fase 2** — komponen (part) sebagai aset, riwayat pemasangan (selesai) |
| 15 | [feedback-dan-tindak-lanjut.md](15-feedback-dan-tindak-lanjut.md) | **Fase 3** — umpan balik pengguna & tindak lanjut (selesai) |

## Cara Memakai Dokumentasi Ini

1. Baca [01-overview.md](01-overview.md) dan [02-arsitektur.md](02-arsitektur.md) sebelum menulis kode.
2. Implementasi migrasi mengikuti [03-database.md](03-database.md).
3. Setiap modul (05–08) berisi daftar file yang harus dibuat, route, dan aturan bisnisnya.
4. Gunakan [10-validasi.md](10-validasi.md) sebagai acuan tunggal aturan validasi agar tidak terjadi duplikasi logika.
5. Cek [12-acceptance-dan-roadmap.md](12-acceptance-dan-roadmap.md) untuk menentukan status MVP.
6. Untuk modul komponen, baca [14-manajemen-komponen.md](14-manajemen-komponen.md).

## Status

**MVP (Fase 1) — SELESAI**

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

**Fase 2 (Komponen) — SELESAI**

- [x] Fondasi komponen (migrasi, model, enum)
- [x] CRUD Komponen
- [x] Pemasangan & riwayat
- [x] Penyesuaian aset + migrasi data spesifikasi
- [x] Dashboard & hardening

## Status MVP: SELESAI

Seluruh kriteria penerimaan MVP (AC-1 s/d AC-4) terverifikasi. Lihat [12-acceptance-dan-roadmap.md](12-acceptance-dan-roadmap.md)
untuk detail per milestone, dan [13-operasional.md](13-operasional.md) untuk cara menjalankan.

Fase 2 (AC-5 s/d AC-13) **sudah diimplementasikan**: komponen (part) menjadi aset tersendiri
dengan riwayat pemasangan. Lihat [14-manajemen-komponen.md](14-manajemen-komponen.md).

**Fase 3 (umpan balik pengguna) — SELESAI**

- [x] FB-1 Login memakai username (bukan email) + perintah reset kata sandi
- [x] FB-2 Standarisasi penuh Bahasa Indonesia (termasuk label status)
- [x] FB-3 Ringkasan spesifikasi esensial di daftar aset
- [x] FB-4 Jenis aset baru: CCTV & Printer
- [x] FB-5 Generalisasi input komponen
- [x] FB-6 Rakit aset & operasi komponen massal
- [x] FB-7 Struktur sinkron server (external_id, synced_at)
- [x] FB-8 Kredensial Windows & VNC per aset (terenkripsi)

**Fase 3 lanjutan (temuan T1–T7) — SELESAI**

- [x] T1 CCTV tanpa pemilik
- [x] T2 Ringkasan spesifikasi tetap (motherboard, CPU, RAM, storage, GPU)
- [x] T3 OS digabung ke Informasi Aset
- [x] T4 Merek opsional (PC rakitan → "Rakitan")
- [x] T5 Grafik dashboard (Chart.js)
- [x] T6 Sidebar: Menu / Aksi Cepat / Arsip
- [x] T7 Filter kapasitas & tipe komponen

**Fase 3 lanjutan (S1–S5) — SELESAI**

- [x] S1 Dashboard: kartu Total CCTV & Printer
- [x] S2 Filter Storage: tipe + kapasitas
- [x] S3 Manajemen akun (admin/viewer)
- [x] S4 Input komponen berbasis pilihan (RAM/Storage/PSU/Monitor)
- [x] S5 Kredensial jamak per aset (tabel `asset_credentials`)

Rincian, analisis, dan prioritasnya di [15-feedback-dan-tindak-lanjut.md](15-feedback-dan-tindak-lanjut.md).

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
| Hostname komputer | Kolom tersendiri (bukan bagian `specs`), unik antar aset aktif | [03](03-database.md), [06](06-manajemen-aset.md) |
| **Komponen komputer (Fase 2)** | **Menjadi aset tersendiri** di tabel `components`, dipasang ke host lewat `component_installations`, riwayat perpindahan terlacak | [14](14-manajemen-komponen.md) |
| **Penyimpanan komponen (Fase 2)** | Tabel terpisah, bukan satu tabel `assets` + `kind` — atribut berbeda & constraint host tetap kuat | [14](14-manajemen-komponen.md) §2 |
| **Granularitas komponen (Fase 2)** | Per **unit fisik** (1 keping = 1 baris) agar perpindahan keping terlacak | [14](14-manajemen-komponen.md) §2 |
| **`specs` aset (Fase 2)** | Tinggal `os`; part fisik pindah ke komponen, ringkasan HW dihitung otomatis | [14](14-manajemen-komponen.md) §9 |
| **Kategori komponen (Fase 2)** | Internal (cpu/ram/storage/gpu/motherboard/psu/casing) + peripheral (monitor/keyboard/mouse) + `other` | [14](14-manajemen-komponen.md) §3 |
| **OS bukan komponen (Fase 2)** | OS perangkat lunak, tetap atribut host | [14](14-manajemen-komponen.md) §3 |
| **Folder view komponen (Fase 2)** | `resources/views/parts/` — `views/components/` sudah dipakai Blade component | [02](02-arsitektur.md) §3 |
