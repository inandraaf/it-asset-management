# 12 — Kriteria Penerimaan & Roadmap

## 1. Definisi MVP Selesai

MVP dianggap selesai jika **seluruh** kriteria berikut terpenuhi dan terverifikasi lewat test otomatis atau pengujian manual tercatat.

## 2. Kriteria Penerimaan (Acceptance Criteria)

### AC-1 — Alur Bahagia Assign

> Admin sukses menambahkan PC baru dan menugaskannya ke user "Budi" di departemen "RnD".

**Langkah uji:**

1. Login sebagai admin.
2. Tambah departemen "RnD" (bila belum ada).
3. Tambah karyawan "Budi" dengan `department_id = RnD`.
4. Tambah aset PC dengan MAC unik; pastikan kode aset ter-generate.
5. Assign aset ke Budi.

**Ekspektasi:**

- Aset tersimpan dengan `status = Available`.
- Setelah assign, `status = Assigned`.
- Baris `asset_assignments` dibuat dengan `employee_id = Budi`, `returned_date = NULL`.

**Test:** `tests/Feature/AssetTest.php::test_admin_can_create_asset_with_generated_code`,
`tests/Feature/AssetSearchTest.php::test_search_by_holder_name`,
`tests/Feature/AssetAssignmentTest.php::test_admin_can_assign_asset_to_employee` (M5)

---

### AC-2 — Tolak Duplikasi MAC/IP

> Sistem menolak (error) saat Admin memasukkan MAC/IP Address yang sudah dipakai PC lain.

**Langkah uji:**

1. Buat aset A dengan MAC `AA:BB:CC:DD:EE:01` dan IP `192.168.1.10`.
2. Buat aset B dengan MAC yang sama.
3. Buat aset C dengan IP yang sama.

**Ekspektasi:**

- Kedua percobaan gagal dengan error validasi.
- Tidak ada baris baru di tabel `assets`.
- Pesan: "MAC Address sudah dipakai aset lain." / "IP Address sudah dipakai aset lain."

**Test:** `tests/Feature/AssetTest.php::test_duplicate_mac_is_rejected`, `test_duplicate_ip_is_rejected`, `test_multiple_assets_can_have_null_ip`

---

### AC-3 — Transfer & Riwayat

> Saat PC ditarik dari "Budi" lalu diserahkan ke "Andi", log sistem mencatat "Budi" di riwayat dan status current user beralih ke "Andi".

**Langkah uji (dua varian):**

- **Varian 1 — Return lalu Assign:** Return aset tanggal 31 Mar 2026, lalu Assign ke Andi tanggal 1 Apr 2026.
- **Varian 2 — Transfer satu klik:** dari detail aset, klik Transfer → pilih Andi → tanggal 1 Apr 2026.

**Ekspektasi (kedua varian sama):**

- Riwayat berisi **dua** baris: Budi (`returned_date = 31 Mar 2026`) dan Andi (`returned_date = NULL`).
- Halaman detail aset menampilkan pemegang saat ini = Andi.
- `status = Assigned`.

**Test:** `tests/Feature/AssetAssignmentTest.php::test_asset_history_records_previous_and_current_holder`, `test_transfer_creates_two_history_rows_in_one_action`

---

### AC-4 — Dashboard

> Dashboard menampilkan ringkasan: Total PC, Total Laptop, Aset Terpakai, dan Aset Menganggur.

**Ekspektasi:** empat angka tampil dan nilainya konsisten dengan isi database.

**Test:** `tests/Feature/DashboardTest.php::test_dashboard_shows_the_four_required_summary_labels`,
`test_stats_count_assets_by_type_and_status`, `test_stats_change_after_assign_and_return`,
`test_department_summary_counts_employees_and_assigned_assets`

---

## 2b. Kriteria Penerimaan Fase 2 (Komponen)

Desain lengkap: [14-manajemen-komponen.md](14-manajemen-komponen.md).

> AC-5 s/d AC-13 terdaftar di [14-manajemen-komponen.md](14-manajemen-komponen.md) §14.
> Ringkasnya: komponen punya kode otomatis (AC-5), bisa dipasang/dilepas dengan riwayat
> (AC-6, AC-7), transfer antarmesin menghasilkan dua baris riwayat (AC-8), pemasangan
> ganda/tidak valid ditolak (AC-9, AC-10), ringkasan PC ikut berubah (AC-11), hapus PC
> berkomponen ditolak (AC-12), dan viewer hanya membaca (AC-13).

## 3. Matriks Kriteria → Modul

| Kriteria | Dokumen Terkait | Modul Kode |
| --- | --- | --- |
| AC-1 Assign PC ke Budi | [05](05-master-data.md), [06](06-manajemen-aset.md), [07](07-alokasi-aset.md) | Department, Employee, Asset, Assignment |
| AC-2 Tolak duplikasi MAC/IP | [03](03-database.md), [10](10-validasi.md) | AssetRequest, constraint DB |
| AC-3 Transfer & riwayat | [07](07-alokasi-aset.md) | AssetAllocationService, AssetAssignment |
| AC-4 Dashboard | [08](08-dashboard.md) | DashboardController |
| AC-5 … AC-13 Komponen (Fase 2) | [14](14-manajemen-komponen.md) | Component, ComponentInstallation, ComponentAllocationService |

## 4. Roadmap Milestone

### M1 — Fondasi Database

- [x] Migrasi `departments`, `employees`, `assets`, `asset_assignments`
- [x] Migrasi tambahan `role` pada `users`
- [x] CHECK constraint & index (termasuk partial unique untuk soft delete & assignment aktif)
- [x] Model + relasi + cast enum
- [x] Factory & seeder (5 departemen, 1 admin)

**Exit criteria:** `php artisan migrate:fresh --seed` sukses; relasi dapat diuji lewat Tinker. ✅ Terpenuhi.

> Catatan keamanan hasil review M1: default role diubah ke `viewer`, `role` dikeluarkan dari `$fillable`, registrasi publik dimatikan, dan password seeder diambil dari `SEED_ADMIN_PASSWORD`.

### M2 — Autentikasi & Otorisasi

- [x] Verifikasi Breeze berjalan
- [x] Middleware `EnsureUserHasRole` + alias `role`
- [x] Seeder admin
- [x] Matikan registrasi publik
- [x] Sembunyikan menu tulis untuk viewer

**Exit criteria:** viewer mendapat `403` pada route tulis; anonim dialihkan ke login. ✅ Terpenuhi.

> Catatan: route modul (assets/employees/departments) baru dibuat di M3/M4,
> sehingga pengujian middleware `role` memakai route uji yang didaftarkan
> runtime di dalam test. Tautan navigasi memakai `Route::has()` supaya tidak
> error sebelum route tersebut ada.

### M3 — Master Data

- [x] CRUD Departemen
- [x] CRUD Karyawan + filter departemen
- [x] Proteksi hapus (relasi masih dipakai)

**Exit criteria:** AC terkait di [05](05-master-data.md) lulus. ✅ Terpenuhi.

> Catatan implementasi:
> - Route resource memakai `whereNumber()` agar `/employees/create` tidak
>   tertangkap sebagai `/employees/{employee}` (menyebabkan 500).
> - Keunikan nama departemen case-insensitive tidak bisa memakai rule `unique`
>   bawaan; dicek manual dengan `LOWER()` di `withValidator()`.
> - Karyawan dengan riwayat assignment tidak dapat dihapus agar audit tetap utuh
>   (FK `restrictOnDelete`), ditangani dengan pesan jelas di controller.

### M4 — Manajemen Aset

- [x] Generator kode aset
- [x] CRUD Aset + `StoreAssetRequest`/`UpdateAssetRequest`
- [x] Custom rule MAC
- [x] Pencarian (nama/IP/MAC) + filter (status/type/departemen)
- [x] Badge status & halaman detail dasar

**Exit criteria:** AC-2 lulus. ✅ Terpenuhi.

> Catatan: generator kode memakai `pg_advisory_xact_lock` (bukan `lockForUpdate`)
> karena nomor urut dihitung dari baris yang belum tentu ada. Soft delete +
> restore aset juga mencakup validasi ulang konflik MAC/IP saat pemulihan.

### M5 — Alokasi & Riwayat

- [x] `AssetAllocationService` (assign/return/transfer, transaksi + lock)
- [x] Form assign, return, dan transfer
- [x] Tabel riwayat di detail aset
- [x] Proteksi assign ganda & status tidak valid
- [x] Soft delete aset + partial unique index + halaman restore

**Exit criteria:** AC-1 dan AC-3 lulus (termasuk varian transfer satu klik). ✅ Terpenuhi.

> Catatan: form Return dibuka sebagai panel inline di halaman detail aset
> (bukan halaman terpisah) agar tanggal return dan catatan tetap dapat diisi.
> Transfer memanggil `return()` + `assign()` di dalam satu transaksi, sehingga
> kegagalan di langkah mana pun tidak meninggalkan perubahan parsial —
> dibuktikan oleh `test_transfer_rolls_back_on_failure`.

### M6 — Dashboard & Penyempurnaan

- [x] Kartu statistik + ringkasan per departemen
- [x] Alokasi terbaru
- [x] Flash message, empty state, responsivitas
- [x] Audit N+1 & index

**Exit criteria:** AC-4 lulus; semua target performa [11](11-non-fungsional.md) terpenuhi. ✅ Terpenuhi.

> Catatan: statistik dihitung langsung setiap request (tanpa cache) agar tidak ada
> angka basi setelah assign/return. Dashboard memakai 8 query konstan, tidak
> tumbuh mengikuti jumlah baris. Ringkasan "Aset Terpakai" per departemen
> menghitung baris assignment aktif, bukan jumlah karyawan.

### M7 — Hardening & Rilis

- [x] Seluruh feature test hijau
- [x] `./vendor/bin/pint` bersih
- [x] Uji manual lintas browser
- [x] Backup & dokumentasi operasional

**Exit criteria:** semua kriteria penerimaan lulus dan sistem siap dirilis.

> Catatan audit akhir (M7):
>
> | Audit | Hasil |
> | --- | --- |
> | Otorisasi guest | Semua route terproteksi → 302 ke login; `/register` → 404 |
> | Otorisasi viewer | Route baca 200; route tulis (GET & POST/PUT/DELETE) → 403; data tidak berubah |
> | AC-1 Assign | PC dibuat dengan kode ter-generate, assign ke Budi (RnD) → status `Assigned`, 1 baris riwayat |
> | AC-2 Duplikasi | MAC & IP duplikat ditolak; jumlah aset tidak bertambah |
> | AC-3 Transfer | Budi → Andi satu klik → 2 baris riwayat, pemegang saat ini Andi |
> | AC-4 Dashboard | Angka cocok persis dengan isi database |
> | Performa | Query konstan 6/4/2/8 untuk assets/employees/departments/dashboard — tidak tumbuh saat data ditambah (bukan N+1) |
> | Test | 157 lulus (446 assertions) |
> | Pint | PASS (117 file) |

> **Catatan:** angka di atas adalah **snapshot saat MVP (M7)** dan sengaja dibiarkan
> sebagai catatan historis. Angka final setelah Fase 2 & 3 (komponen + umpan balik
> FB-1…FB-8, T1–T7, R1–R6, S1–S5, U1–U4, V1–V2, W1–W4b) ada di §Catatan Audit Final
> di bawah.

## 5. Rencana Pengujian

| Level | Cakupan | Alat |
| --- | --- | --- |
| Feature | Route, otorisasi, CRUD, assign/return | PHPUnit + `RefreshDatabase` |
| Unit | `AssetCodeGenerator`, custom `MacAddress` rule | PHPUnit |
| Manual | UI responsif, pesan error, alur end-to-end | Checklist QA |

### Contoh Skeleton Test

```php
// tests/Feature/AssetValidationTest.php
use App\Models\{Asset, User};
use Illuminate\Foundation\Testing\RefreshDatabase;

class AssetValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_mac_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Asset::factory()->create(['mac_address' => 'AA:BB:CC:DD:EE:01']);

        $this->actingAs($admin)
            ->post(route('assets.store'), [
                'type' => 'PC',
                'brand' => 'Dell',
                'mac_address' => 'AA:BB:CC:DD:EE:01',
                'specs' => ['cpu' => 'i5'],
            ])
            ->assertSessionHasErrors('mac_address');

        $this->assertDatabaseCount('assets', 1);
    }
}
```

## 6. Di Luar Cakupan MVP (Backlog)

- Role & permission granular (Spatie Permission).
- Ticketing perbaikan & maintenance history.
- Depresiasi & nilai buku aset.
- Pencatatan aksesori/consumables habis pakai (tinta, kabel, thermal paste).
- Validasi kompatibilitas komponen otomatis (DDR4 vs DDR5, socket CPU).
- Import/export Excel (migrasi dari data lama).
- Notifikasi email saat assign.
- Barcode/QR label aset & komponen.
- Multi-lokasi / multi-cabang.
- Audit log menyeluruh (Spatie Activitylog).
- Pencarian aset berdasarkan nomor seri komponen.

## 6b. Fase 2 — Manajemen Komponen

Setelah MVP selesai, lingkup diperluas: **komponen (part) menjadi aset tersendiri** yang
bisa dipasang/dilepas dan dilacak perpindahannya antarmesin. Desain lengkap:
**[14-manajemen-komponen.md](14-manajemen-komponen.md)**.

### F2-1 — Fondasi Komponen ✅

- [x] Migrasi `components` + `component_installations` + CHECK + partial unique index
- [x] Enum `ComponentCategory`, `ComponentStatus`
- [x] Model `Component`, `ComponentInstallation` + relasi
- [x] Generalisasi `AssetCodeGenerator` → `CodeGenerator`

**Exit criteria:** `migrate:fresh --seed` sukses; relasi & constraint terverifikasi.

### F2-2 — CRUD Komponen ✅

- [x] `StoreComponentRequest` / `UpdateComponentRequest` (field menyesuaikan kategori)
- [x] `ComponentController` (index, create, store, show, edit, update, destroy, trashed, restore)
- [x] View `parts/{index,create,edit,show,trashed}` + menu sidebar
- [x] Pencarian & filter kategori/status

**Exit criteria:** AC-5 lulus.

### F2-3 — Pemasangan & Riwayat ✅

- [x] `ComponentAllocationService` (install / remove / move, transaksi + lock)
- [x] `ComponentInstallationController` + request
- [x] Bagian "Komponen Terpasang" di detail aset
- [x] Riwayat pemasangan di detail komponen

**Exit criteria:** AC-6 … AC-10 lulus.

### F2-4 — Penyesuaian Aset & Migrasi Data ✅

- [x] `assets.specs` → hanya `os`; part dihapus dari `Asset::SPEC_KEYS`
- [x] Ringkasan perangkat keras dihitung dari komponen terpasang
- [x] Guard hapus permanen aset yang masih punya komponen
- [x] Perintah `components:import-from-specs --dry-run`
- [x] `ComponentSeeder` + penyesuaian `AssetSeeder`

**Exit criteria:** AC-11 dan AC-12 lulus.

### F2-5 — Dashboard & Hardening ✅

- [x] Kartu Total Komponen & Komponen di Gudang
- [x] Audit N+1 (eager load komponen pada daftar aset)
- [x] Feature test lengkap + Pint bersih

**Exit criteria:** AC-13 lulus; seluruh test hijau.

## 6c. Fase 3 — Tindak Lanjut Umpan Balik Pengguna ✅

Umpan balik dari rekan & Admin IT sudah diidentifikasi dan **seluruhnya diimplementasikan**.
Lihat **[15-feedback-dan-tindak-lanjut.md](15-feedback-dan-tindak-lanjut.md)**.

| Item | Isi | Usaha | Prioritas | Status |
| --- | --- | --- | --- | --- |
| FB-1 | Login memakai **username**, bukan email | Sedang | Tinggi | ✅ |
| FB-2 | Bahasa distandarkan **full Bahasa Indonesia** | Sedang | Tinggi | ✅ |
| FB-3 | Ringkasan spesifikasi esensial (CPU/RAM/disk/motherboard) di daftar aset | Sedang | Tinggi | ✅ |
| FB-4 | Jenis aset baru: **CCTV** & **Printer** (melekat departemen, bisa PIC karyawan) | Besar | Tinggi | ✅ |
| FB-5 | **Generalisasi input komponen** (cukup seri/kapasitas/tipe) | Sedang | Tinggi | ✅ |
| FB-6 | **Rakit aset** (komponen baru + dari gudang) & operasi **bulk** | Besar | Tinggi | ✅ |
| FB-7 | Master data: **siapkan struktur** sinkron server (bulk dibatalkan) | Kecil | Sedang | ✅ |
| FB-8 | **Kredensial Windows & VNC** per aset (terenkripsi, admin-only) | Sedang | Tinggi | ✅ |

Urutan yang diusulkan: **FB-2 → FB-5 → FB-3 → FB-8 → FB-1 → FB-7 → FB-6 → FB-4**
(FB-5 menjadi fondasi FB-3 dan FB-6).

> Seluruh keputusan produk **sudah dijawab** — lihat
> [15-feedback-dan-tindak-lanjut.md](15-feedback-dan-tindak-lanjut.md) §Keputusan Final.

### Fase 3 Lanjutan — Temuan T1–T7 ✅

| # | Temuan | Status |
| --- | --- | --- |
| T1 | CCTV tanpa pemilik (hanya Printer melekat departemen) | ✅ |
| T2 | Ringkasan spesifikasi tetap: motherboard → CPU → RAM → storage → GPU | ✅ |
| T3 | OS digabung ke card Informasi Aset | ✅ |
| T4 | Merek opsional (PC rakitan → "Rakitan") | ✅ |
| T5 | Grafik dashboard (Chart.js, di-bundle Vite) | ✅ |
| T6 | Sidebar: Menu / Aksi Cepat / Arsip | ✅ |
| T7 | Filter kapasitas & tipe komponen dari data aktual | ✅ |

Rincian di [15-feedback-dan-tindak-lanjut.md](15-feedback-dan-tindak-lanjut.md) §Temuan Lanjutan.

### Fase 3 Lanjutan — Temuan S1–S5 ✅

| # | Permintaan | Status |
| --- | --- | --- |
| S1 | Dashboard: kartu Total CCTV & Printer | ✅ |
| S2 | Filter Storage: tipe **dan** kapasitas | ✅ |
| S3 | Manajemen akun (admin IT superadmin, user read-only) | ✅ |
| S4 | Input komponen RAM/Storage/PSU/Monitor jadi pilihan | ✅ |
| S5 | Kredensial lebih dari satu per aset | ✅ |

Rincian di [15-feedback-dan-tindak-lanjut.md](15-feedback-dan-tindak-lanjut.md) §S1–S5.

### Fase 3 Lanjutan — Temuan U1–U4 ✅

| # | Permintaan | Status |
| --- | --- | --- |
| U1 | Filter storage: per keping **dan** total | ✅ |
| U2 | Kredensial dapat dilihat viewer | ✅ |
| U3 | CCTV/Printer: sembunyikan bagian komputer, merek wajib, OS hidden di form | ✅ |
| U4 | Satu karyawan memegang banyak aset (sudah didukung) | ✅ |
| V1 | Filter komponen bertingkat (Kategori → Atribut → Nilai) | ✅ |
| V2 | Dropdown Atribut hanya tampil untuk Storage | ✅ |
| W1 | Bug spesifikasi RAM/Storage hilang saat input (duplikat `name`) | ✅ |
| W2 | Tombol Back menampilkan form basi → risiko duplikat | ✅ |
| W3 | Urutan daftar stabil + aset/komponen baru disorot sekali | ✅ |
| W4 | Urutan daftar menurut jenis (PC → Laptop → CCTV → Printer), lalu kode aset | ✅ |
| W4b | Seeder idempoten walau MAC kosong (kunci pindah ke `hostname`) | ✅ |

## 6d. Catatan Audit Final (setelah Fase 2 & 3)

Angka final seluruh proyek, dijalankan **setelah semua item FB-1…FB-8, T1–T7, R1–R6,
S1–S5, U1–U4, V1–V2, dan W1–W4b selesai**.

| Audit | Hasil |
| --- | --- |
| Test | **366 lulus** (1159 assertions), 24 berkas test |
| Deprecation | 2 (dari PHP 8.5: `PDO::MYSQL_ATTR_SSL_CA` di `config/database.php`) — **bukan kegagalan** |
| Pint | **PASS (160 file)** |
| Seeder | PC=10 · Laptop=6 · CCTV=3 · Printer=2 — total **21 aset**; idempoten (dijalankan 2× tidak menggandakan) |

**Cara menjalankan ulang:**

```bash
DB_HOST=127.0.0.1 php artisan test    # Postgres Docker: nama host "postgres16" tidak resolvable dari host
./vendor/bin/pint --test
```

> Catatan: `DB_HOST` di `.env` adalah `postgres16` (nama service Docker). Dari terminal host,
> nama itu tidak bisa di-resolve sehingga seluruh test gagal dengan
> `could not translate host name "postgres16"`. Override ke `127.0.0.1` saat menjalankan test
> dari host, karena port `5432` sudah dipublikasikan ke localhost.

## 7. Risiko & Mitigasi

| Risiko | Dampak | Mitigasi |
| --- | --- | --- |
| Race condition saat assign bersamaan | Dua assignment aktif | Transaksi + `lockForUpdate` + partial unique index |
| Data Excel lama tidak konsisten | Migrasi kotor | Validasi ketat saat import (backlog) |
| MAC format campuran | Pencarian gagal | Normalisasi uppercase di `prepareForValidation` |
| Aset dihapus menghilangkan riwayat | Kehilangan audit | Soft delete / `restrictOnDelete` |
| Pencarian lambat saat data besar | UX buruk | Index + `pg_trgm` bila perlu |
