# 06 — Manajemen Aset (CRUD)

## 1. Field Aset

| Field | Tipe | Wajib | Aturan |
| --- | --- | --- | --- |
| `asset_code` | string(30) | **Otomatis** | Dibuat sistem; form tidak menerima input kode |
| `type` | enum | Ya | `PC` atau `Laptop` |
| `brand` | string(100) | Ya | Merek & model (Dell OptiPlex 7090, dll) |
| `hostname` | string(63) | Tidak | Nama komputer di jaringan/Windows. Unik antar aset aktif, disimpan lowercase |
| `mac_address` | string(17) | Ya | **Unik global**, format `AA:BB:CC:DD:EE:FF` |
| `ip_address` | string(45) | Tidak | **Unik jika diisi**, IPv4/IPv6 valid |
| `specs` | object | Tidak | **Fase 2:** hanya `os` (opsional). Part fisik dikelola di modul komponen |
| `status` | enum | Ya | Default `Available` |
| `created_by` | bigint | auto | Diisi dari `auth()->id()` |

**Fase 2:** part fisik (cpu, ram, storage, gpu, motherboard, psu, casing, monitor, keyboard, mouse) tidak lagi di `specs` — dikelola sebagai komponen tersendiri di [14-manajemen-komponen.md](14-manajemen-komponen.md). `specs` hanya menyimpan `os`.

Detail aturan validasi lengkap ada di [10-validasi.md](10-validasi.md).

## 2. Auto-generate Kode Aset

Format usulan: `{PREFIX}-{TAHUN}-{URUT 4 DIGIT}`

| Jenis | Prefix | Contoh |
| --- | --- | --- |
| PC | `PC` | `PC-2026-0001` |
| Laptop | `LT` | `LT-2026-0007` |

```php
// app/Services/AssetCodeGenerator.php
class AssetCodeGenerator
{
    public function next(AssetType $type): string
    {
        $prefix = $type->codePrefix();          // PC / LT
        $year   = now()->year;

        // Kunci per (prefix, tahun). Lock baris tidak bisa dipakai karena
        // baris yang dihitung belum tentu ada (phantom insert).
        DB::select('SELECT pg_advisory_xact_lock(?)', [crc32("asset_code:$prefix:$year")]);

        $last = Asset::withTrashed()
            ->where('asset_code', 'like', "$prefix-$year-%")
            // Urutkan berdasarkan NILAI numerik, bukan teks. Secara teks
            // "PC-2026-9999" > "PC-2026-10000", sehingga urutan leksikografis
            // salah begitu urutan melewati 4 digit.
            ->orderByRaw("CAST(SPLIT_PART(asset_code, '-', 3) AS INTEGER) DESC")
            ->value('asset_code');

        $seq = $last ? ((int) Str::afterLast($last, '-')) + 1 : 1;

        return sprintf('%s-%d-%04d', $prefix, $year, $seq);
    }
}
```

Aturan:

- Field `asset_code` pada form **opsional**; bila dikosongkan, sistem men-generate otomatis. Jika diisi manual, tetap divalidasi unik.
- **Generate dan simpan harus berada dalam satu transaksi** (`DB::transaction`). `next()` menghitung dari aset yang sudah tersimpan, jadi memanggil `next()` dua kali tanpa menyimpan akan mengembalikan kode yang sama.
- Penguncian memakai `pg_advisory_xact_lock` (otomatis dilepas saat transaksi selesai) untuk mencegah dua request bersamaan menerima nomor urut identik.
- `withTrashed()` **wajib**: tanpa itu, kode aset yang sudah di-soft delete bisa diterbitkan ulang (lihat catatan "Implikasi asset_code" di [03-database.md](03-database.md)).

## 3. Route

```php
Route::middleware('auth')->group(function () {
    Route::get('assets', [AssetController::class, 'index'])->name('assets.index');
    Route::get('assets/{asset}', [AssetController::class, 'show'])->name('assets.show');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('assets/create', [AssetController::class, 'create'])->name('assets.create');
    Route::post('assets', [AssetController::class, 'store'])->name('assets.store');
    Route::get('assets/{asset}/edit', [AssetController::class, 'edit'])->name('assets.edit');
    Route::put('assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
    Route::delete('assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');
});
```

> **Penting**: route `assets/{asset}` (show) harus dideklarasikan **setelah** `assets/create`, atau gunakan constraint, agar "create" tidak dianggap sebagai `{asset}`.

## 4. Form Tambah Aset

Urutan field yang disarankan:

1. **Jenis** (radio: PC / Laptop) — memicu generate prefix.
2. **Kode Aset** (readonly + tombol Generate).
3. **Merek**.
4. **MAC Address** (placeholder `AA:BB:CC:DD:EE:FF`, auto-uppercase saat blur).
5. **IP Address** (opsional).
6. **Spesifikasi**: CPU, RAM, Storage, OS (masing-masing input terpisah, digabung ke `specs` JSON).
7. **Status** (default `Available`; saat create sebaiknya dikunci ke `Available`).

Alpine.js dipakai untuk:

- Auto-format MAC menjadi huruf kapital dan menyisipkan `:` tiap 2 karakter.
- Menampilkan contoh kode aset sesuai jenis.

## 5. Form Edit Aset

Boleh diubah: `brand`, `ip_address`, `specs`, `status`, `mac_address` (jarang, dengan validasi unik).

Aturan tambahan:

- `type` **tidak boleh diubah** setelah aset dibuat (menjaga konsistensi kode aset). Jika perlu, nonaktifkan field dan tampilkan sebagai teks.
- Jika status aset `Assigned`, IP boleh diubah tetapi **status tidak boleh diubah manual ke `Available`** — harus lewat proses Return agar riwayat tetap konsisten.

## 6. Daftar Aset (Index)

### Kolom Tabel

| Kolom | Isi |
| --- | --- |
| Kode Aset | link ke detail |
| Jenis | badge (PC / Laptop) |
| Merek | |
| MAC Address | monospace |
| IP Address | atau `-` |
| Pengguna Saat Ini | nama karyawan + departemen, atau `-` |
| Status | badge berwarna |
| Aksi | Detail / Edit / Hapus |

### Warna Badge Status

| Status | Warna |
| --- | --- |
| `Available` | hijau |
| `Assigned` | biru |
| `In Repair` | kuning |
| `Retired` | abu-abu |

### Pencarian & Filter

Wajib mendukung (dari PRD):

- Cari berdasarkan **nama user** → join ke assignment aktif → employee.
- Cari berdasarkan **IP Address**.
- Cari berdasarkan **MAC Address**.
- Filter berdasarkan **status**.
- Filter berdasarkan **departemen**.

```php
$assets = Asset::query()
    ->with(['activeAssignment.employee.department'])
    ->when($request->filled('q'), function ($q) use ($request) {
        $term = '%'.$request->string('q').'%';
        $q->where(function ($w) use ($term) {
            $w->where('asset_code', 'ilike', $term)
              ->orWhere('mac_address', 'ilike', $term)
              ->orWhere('ip_address', 'ilike', $term)
              ->orWhere('brand', 'ilike', $term)
              ->orWhereHas('activeAssignment.employee', fn ($e) => $e->where('nama', 'ilike', $term));
        });
    })
    ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
    ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
    ->when($request->filled('department_id'), function ($q) use ($request) {
        $q->whereHas('activeAssignment.employee',
            fn ($e) => $e->where('department_id', $request->integer('department_id')));
    })
    ->orderByDesc('created_at')
    ->paginate(20)
    ->withQueryString();
```

> Catatan: pencarian `ip_address` hanya cocok untuk filter, bukan relasi. Gunakan operator `ilike` untuk performa dan fleksibilitas.

## 7. Hapus Aset

- Hapus hanya diizinkan jika aset **tidak sedang di-assign** (tidak ada assignment aktif).
- Alternatif yang lebih aman: arahkan admin untuk mengubah status menjadi `Retired` daripada menghapus. Sediakan konfirmasi modal.
- Memakai **soft delete** (`deleted_at`): data tidak benar-benar hilang, sehingga kesalahan hapus masih bisa dipulihkan.
- Karena soft delete, unique index `mac_address` / `ip_address` / `asset_code` dibuat **partial** (`WHERE deleted_at IS NULL`) dan aturan `Rule::unique()` harus ditambah `->whereNull('deleted_at')`.

### Alur Hapus & Restore

| Aksi | Efek |
| --- | --- |
| Hapus | `deleted_at` diisi; aset hilang dari daftar normal |
| Lihat terhapus | Filter "Tampilkan yang dihapus" pada index (khusus admin) |
| Restore | `deleted_at` dikosongkan, **setelah** cek ulang konflik `asset_code`, `mac_address`, dan `ip_address` terhadap aset aktif |
| Hapus permanen | `forceDelete()`, hanya untuk admin, dengan konfirmasi ganda |

### Proteksi Tambahan

- Aset `Assigned` **tidak boleh** dihapus (baik soft maupun hard) — harus Return dahulu.
- Aset yang di-soft delete tidak dihitung di dashboard (query default tidak menyertakan `trashed`).
- Sediakan halaman/list aset terhapus agar restore dapat dilakukan tanpa akses database.

## 8. Relasi Model

```php
class Asset extends Model
{
    protected $fillable = ['asset_code', 'type', 'brand', 'mac_address', 'ip_address', 'specs', 'status', 'created_by'];

    protected $casts = [
        'specs'  => 'array',
        'type'   => AssetType::class,
        'status' => AssetStatus::class,
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class)->orderByDesc('assigned_date');
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(AssetAssignment::class)->whereNull('returned_date');
    }

    public function scopeAvailable($q): Builder
    {
        return $q->where('status', AssetStatus::Available);
    }
}
```

## 9. Acceptance Terkait

- [x] Admin dapat menambahkan PC baru dengan kode aset ter-generate otomatis.
- [x] Sistem menolak MAC Address yang sudah dipakai aset lain (error validasi, bukan 500).
- [x] Sistem menolak IP Address yang sudah dipakai aset lain.
- [x] IP kosong (`null`) boleh diisi pada banyak aset tanpa error.
- [x] Pencarian nama user, IP, dan MAC mengembalikan hasil yang benar.
- [x] Filter status dan departemen bekerja bersamaan dengan pencarian.
- [x] Soft delete aset + restore, dengan validasi ulang konflik MAC/IP saat restore.
- [x] Viewer hanya dapat membaca (route tulis → 403).

### Catatan Implementasi

| Topik | Keputusan |
| --- | --- |
| Kode aset | **Selalu dibuat sistem.** Form tidak punya input kode; nilai final dihitung di dalam transaksi saat menyimpan. Input manual dari luar diabaikan. |
| Status saat create | Dipaksa `Available` di controller, apa pun input form. |
| `type`, `asset_code`, `hostname` | Tidak ikut divalidasi pada `UpdateAssetRequest`, sehingga tidak bisa diubah lewat form edit. |
| Hostname | Kolom tersendiri (bukan bagian `specs`) karena sering dipakai untuk mencari PC. Disimpan lowercase, unik antar aset aktif, opsional. |
| Router | `assets/create` dan `assets/trashed` dideklarasikan sebelum `{asset}`, ditambah `whereNumber('asset')`. |
| Pencarian | Karakter `%` dan `_` di-escape agar tidak jadi wildcard tak terduga. Mencakup kode, hostname, MAC, IP, merek, dan nama pemegang. |
| Hapus permanen | Ditolak bila aset punya riwayat assignment (riwayat akan ikut terhapus oleh `cascadeOnDelete`). |

## 11. Keputusan: Bagaimana Part Komputer Dicatat?

> **STATUS: DIREVISI.** Keputusan awal memakai **Model A** (komponen sebagai field di
> `assets.specs`). Setelah kebutuhan pelacakan perpindahan part muncul, keputusan berubah ke
> **Model B** (komponen sebagai aset tersendiri). Desain lengkapnya ada di
> **[14-manajemen-komponen.md](14-manajemen-komponen.md)**. Bagian di bawah disimpan sebagai
> catatan sejarah keputusan.

Pertanyaan yang wajar: **apakah RAM, GPU, dan komponen lain juga "aset"?**

| Model | Cara kerja | Menjawab |
| --- | --- | --- |
| **A. Komponen sebagai field** (awalnya dipakai) | Komponen jadi key di `assets.specs` | "PC ini isinya apa?" |
| **B. Komponen sebagai aset** (**dipakai sekarang**) | Komponen jadi baris tersendiri di tabel `components`, dipasang ke host lewat `component_installations` | "Keping RAM ini sekarang di mana, sebelumnya di mana saja?" |

### Alasan beralih ke Model B

1. **Kebutuhan pelacakan perpindahan.** Model A tidak bisa menjawab di mana sebuah keping
   komponen berada dan ke mana saja ia pernah dipindah.
2. **Komponen punya identitas sendiri** (nomor seri, kapasitas) yang perlu dicatat per unit.
3. **Riwayat pemasangan** dibutuhkan untuk audit dan kanibalisasi PC rusak.

### Yang berubah pada modul aset

- `assets.specs` disederhanakan menjadi **hanya `os`** (perangkat lunak, bukan benda fisik) — **sudah diterapkan**.
- Part fisik dikelola di modul komponen.
- **Ringkasan perangkat keras** PC dihitung otomatis dari komponen terpasang.

> Ruang lingkup PRD diperluas: pencatatan komponen kini **termasuk**. Lihat
> [01-overview.md](01-overview.md) §4 dan [14-manajemen-komponen.md](14-manajemen-komponen.md).
