# 08 — Dashboard & Reporting

## 1. Ringkasan yang Wajib Ditampilkan

Empat kartu statistik (dari Acceptance Criteria):

| Kartu | Query |
| --- | --- |
| **Total PC** | `Asset::where('type','PC')->count()` |
| **Total Laptop** | `Asset::where('type','Laptop')->count()` |
| **Aset Terpakai** | `Asset::where('status','Assigned')->count()` |
| **Aset Menganggur** | `Asset::where('status','Available')->count()` |

Kartu tambahan yang disarankan: `In Repair`, `Retired`, `Total Karyawan`, `Total Departemen`.

## 2. Controller

```php
// app/Http/Controllers/DashboardController.php
public function __invoke(): View
{
    // Satu query agregat, dipetakan di PHP — menghindari 6 count() terpisah.
    $byTypeAndStatus = Asset::query()
        ->selectRaw('type, status, count(*) as total')
        ->groupBy('type', 'status')
        ->get();

    $count = fn (AssetType $type) => (int) $byTypeAndStatus->where('type', $type)->sum('total');
    $countStatus = fn (AssetStatus $status) => (int) $byTypeAndStatus->where('status', $status)->sum('total');

    $stats = [
        'total_pc'          => $count(AssetType::PC),
        'total_laptop'      => $count(AssetType::Laptop),
        'assigned'          => $countStatus(AssetStatus::Assigned),
        'available'         => $countStatus(AssetStatus::Available),
        'in_repair'         => $countStatus(AssetStatus::InRepair),
        'retired'           => $countStatus(AssetStatus::Retired),
        'total_assets'      => (int) $byTypeAndStatus->sum('total'),
        'total_employees'   => Employee::count(),
        'total_departments' => Department::count(),
    ];

    // "Aset Terpakai" per departemen = jumlah assignment aktif, BUKAN jumlah karyawan.
    $byDepartment = Department::query()
        ->withCount([
            'employees',
            'assignments as assigned_assets_count' => fn ($q) => $q->whereNull('returned_date'),
        ])
        ->orderBy('nama_dept')
        ->get();

    $recentAssignments = AssetAssignment::with(['asset', 'employee.department'])
        ->orderByDesc('assigned_date')
        ->orderByDesc('id')
        ->limit(10)
        ->get();

    return view('dashboard', compact('stats', 'byDepartment', 'recentAssignments'));
}
```

> **Koreksi penting.** Contoh `withCount(['employees as assigned_assets_count' => ...])`
> pada versi awal dokumen ini **salah**: ia menghitung jumlah *karyawan* yang punya
> aset aktif, bukan jumlah *aset* terpakai. Satu karyawan bisa memegang dua aset,
> sehingga angkanya berbeda. Implementasi memakai relasi `hasManyThrough`
> `Department::assignments()` (lihat [03-database.md](03-database.md))
> agar yang dihitung benar-benar baris assignment aktif.

## 3. Layout Dashboard

```
┌────────────────────────────────────────────────────────┐
│  Total PC │ Total Laptop │ Terpakai │ Menganggur        │  ← 4 kartu
├────────────────────────────────────────────────────────┤
│  Ringkasan per Departemen (tabel)                      │
├───────────────────────────┬────────────────────────────┤
│  Aset Terbaru             │  Alokasi Terbaru           │
└───────────────────────────┴────────────────────────────┘
```

### Ringkasan per Departemen

| Departemen | Jumlah Karyawan | Aset Terpakai |
| --- | --- | --- |
| EDP | 12 | 10 |
| RnD | 8 | 7 |

### Alokasi Terbaru

Kolom: Karyawan, Aset, Tanggal Assign, Status (Aktif / Selesai).

## 4. Filter Dashboard (opsional)

- Periode tanggal assign.
- Departemen.
- Jenis aset.

Untuk MVP boleh di-skip; cukup tampilkan angka keseluruhan.

## 5. Laporan (Nice-to-have, di luar MVP wajib)

| Laporan | Deskripsi | Format |
| --- | --- | --- |
| Aset per Departemen | Semua aset + pemegang | Tabel / CSV |
| Aset Menganggur | Aset `Available` beserta umur di gudang | Tabel / CSV |
| Riwayat per Karyawan | Semua aset yang pernah dipegang | Tabel |
| Log Alokasi | Seluruh assignment dalam rentang tanggal | Tabel / CSV |

Ekspor CSV: gunakan `response()->streamDownload()` dengan query yang sama seperti tabel (hindari duplikasi logika — pindahkan ke query object / scope).

## 6. Query Performa

- Dashboard tidak boleh memicu N+1; selalu `with()` relasi yang ditampilkan.
- Index `status` dan `type` pada `assets` mendukung `count()` ini.
- **Index pendukung dashboard** (migrasi `2026_01_01_000006`):
  - `asset_assignments (employee_id, returned_date)` — mempercepat `withCount` assignment aktif per departemen.
  - `asset_assignments (assigned_date, id)` — mempercepat daftar "alokasi terbaru" (`ORDER BY assigned_date DESC, id DESC LIMIT 10`) tanpa mengurutkan seluruh riwayat.
- Untuk perusahaan dengan ribuan aset, pertimbangkan cache 60 detik (`Cache::remember('dashboard.stats', 60, ...)`). **MVP tidak memakai cache** agar angka selalu segar setelah assign/return.

## 7. Acceptance Terkait

- [x] Dashboard menampilkan Total PC, Total Laptop, Aset Terpakai, Aset Menganggur.
- [x] Angka berubah dengan benar setelah assign/return (tanpa cache; statistik dihitung langsung setiap request).
- [x] Viewer dapat melihat dashboard (read-only).

### Catatan Implementasi

| Topik | Keputusan |
| --- | --- |
| Statistik aset | Satu query agregat `group by type, status`, dipetakan di PHP (bukan 6 `count()` terpisah). |
| "Aset Terpakai" per departemen | Dihitung dari baris assignment aktif via relasi `hasManyThrough`, bukan jumlah karyawan. |
| Cache | **Tidak** dipakai untuk MVP. Statistik dihitung langsung agar tidak ada angka basi setelah assign/return. |
| Jumlah query | 8 query konstan (tidak tumbuh mengikuti jumlah baris); relasi alokasi terbaru di-eager-load. |
| Aset ter-soft-delete | Otomatis tidak terhitung karena query default mengecualikan `trashed`. |
