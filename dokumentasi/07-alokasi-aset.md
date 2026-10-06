# 07 — Tracking Alokasi Aset (Assign / Return)

Modul ini adalah inti nilai sistem: melacak aset dari IT ke karyawan dan kembali lagi, dengan riwayat lengkap.

## 1. Konsep

| Aksi | Efek pada `assets.status` | Efek pada `asset_assignments` |
| --- | --- | --- |
| **Assign** | `Available` → `Assigned` | Insert baris baru: `assigned_date = hari ini`, `returned_date = NULL` |
| **Return** | `Assigned` → `Available` | Update baris aktif: `returned_date = hari ini` |
| **Transfer** | `Assigned` → `Assigned` | Return + Assign baru dalam satu transaksi (2 baris riwayat) |

### Keputusan Desain: Transfer = Satu Klik, Dua Baris Riwayat

Perpindahan aset dari karyawan A ke karyawan B **selalu** menghasilkan **dua baris** di `asset_assignments`:

1. Baris lama (A) di-`UPDATE` → `returned_date` diisi.
2. Baris baru (B) di-`INSERT` → `returned_date = NULL`.

Namun dari sisi UI, admin **cukup satu klik** lewat tombol **Transfer**. Tombol **Return** tetap disediakan sebagai aksi mandiri untuk kasus aset ditarik tanpa langsung diserahkan ke orang lain (masuk gudang, dikirim `In Repair`, dsb).

Alasan database tetap 2 baris (bukan update `employee_id` pada baris lama):

- Audit trail utuh — jejak Budi tidak hilang (memenuhi AC-3).
- Durasi pemakaian per karyawan dapat dihitung.
- Konsisten dengan invariant "satu assignment aktif per aset".

## 2. Prasyarat

- Hanya aset berstatus **`Available`** yang boleh di-assign.
- Aset `In Repair` dan `Retired` **tidak boleh** di-assign.
- Aset yang sudah punya assignment aktif (`returned_date IS NULL`) tidak boleh di-assign lagi → proteksi ganda lewat partial unique index di database.
- Transfer hanya untuk aset yang sedang berstatus `Assigned` (punya assignment aktif).

## 3. Route

```php
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('assets/{asset}/assign', [AssetAssignmentController::class, 'create'])->name('assets.assign.create');
    Route::post('assets/{asset}/assign', [AssetAssignmentController::class, 'store'])->name('assets.assign.store');
    Route::post('assignments/{assignment}/return', [AssetAssignmentController::class, 'return'])->name('assignments.return');
    Route::get('assets/{asset}/transfer', [AssetAssignmentController::class, 'transferCreate'])->name('assets.transfer.create');
    Route::post('assets/{asset}/transfer', [AssetAssignmentController::class, 'transfer'])->name('assets.transfer.store');
});
```

## 4. Form Assign

Field:

| Field | Wajib | Catatan |
| --- | --- | --- |
| Karyawan | Ya | Dropdown dengan pencarian, menampilkan `nama — departemen` |
| Tanggal Assign | Ya | Default hari ini, tidak boleh di masa depan |
| Catatan | Tidak | Kondisi perangkat, kelengkapan charger, dsb |
| Aset (readonly) | Ya | Menampilkan kode, merek, spesifikasi |

## 5. Form Return

Field:

| Field | Wajib | Catatan |
| --- | --- | --- |
| Aset (readonly) | Ya | Menampilkan pemegang saat ini |
| Tanggal Return | Ya | Default hari ini, tidak boleh sebelum `assigned_date` dan tidak boleh masa depan |
| Catatan | Tidak | Kondisi saat dikembalikan (mis. "layar lecet") |

## 5b. Form Transfer

Ditampilkan dari halaman detail aset yang sedang berstatus `Assigned`.

| Field | Wajib | Catatan |
| --- | --- | --- |
| Aset (readonly) | Ya | Kode + pemegang saat ini (karyawan asal) |
| Karyawan Tujuan | Ya | Dropdown; **karyawan asal harus dikecualikan** dari pilihan |
| Tanggal Transfer | Ya | Default hari ini; menjadi `returned_date` baris lama **dan** `assigned_date` baris baru |
| Catatan | Tidak | Diteruskan sebagai notes assignment baru |

Perilaku setelah submit: kembali ke halaman detail aset dengan flash `Aset {kode} ditransfer dari {asal} ke {tujuan}.`

## 6. Service Layer

Semua logika assign/return/transfer berada di `AssetAllocationService` dan dibungkus transaksi + row lock.

```php
// app/Services/AssetAllocationService.php
class AssetAllocationService
{
    public function assign(Asset $asset, int $employeeId, Carbon $date, ?string $notes): AssetAssignment
    {
        return DB::transaction(function () use ($asset, $employeeId, $date, $notes) {
            $locked = Asset::whereKey($asset->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== AssetStatus::Available) {
                throw ValidationException::withMessages([
                    'asset_id' => 'Aset tidak tersedia untuk di-assign (status saat ini: '.$locked->status->value.').',
                ]);
            }

            if ($locked->assignments()->whereNull('returned_date')->exists()) {
                throw ValidationException::withMessages([
                    'asset_id' => 'Aset masih terpasang pada karyawan lain. Lakukan Return terlebih dahulu.',
                ]);
            }

            $assignment = $locked->assignments()->create([
                'employee_id'   => $employeeId,
                'assigned_date' => $date,
                'notes'         => $notes,
                'assigned_by'   => auth()->id(),
            ]);

            $locked->update(['status' => AssetStatus::Assigned]);

            return $assignment;
        });
    }

    public function return(AssetAssignment $assignment, Carbon $date, ?string $notes): AssetAssignment
    {
        return DB::transaction(function () use ($assignment, $date, $notes) {
            $locked = AssetAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();

            if ($locked->returned_date !== null) {
                throw ValidationException::withMessages([
                    'assignment' => 'Aset ini sudah dikembalikan sebelumnya.',
                ]);
            }

            if ($date->lt($locked->assigned_date)) {
                throw ValidationException::withMessages([
                    'returned_date' => 'Tanggal return tidak boleh sebelum tanggal assign.',
                ]);
            }

            $locked->update([
                'returned_date' => $date,
                'notes'         => trim(($locked->notes ? $locked->notes."\n" : '').($notes ?? '')) ?: $locked->notes,
            ]);

            $locked->asset()->update(['status' => AssetStatus::Available]);

            return $locked->fresh();
        });
    }

    /**
     * Transfer = Return + Assign dalam satu transaksi.
     * Admin cukup satu klik, tetapi menghasilkan 2 baris riwayat.
     */
    public function transfer(Asset $asset, int $newEmployeeId, Carbon $date, ?string $notes): AssetAssignment
    {
        return DB::transaction(function () use ($asset, $newEmployeeId, $date, $notes) {
            $current = AssetAssignment::where('asset_id', $asset->id)
                ->whereNull('returned_date')
                ->lockForUpdate()
                ->first();

            if (! $current) {
                throw ValidationException::withMessages([
                    'asset_id' => 'Aset ini tidak sedang dipegang siapa pun. Gunakan fitur Assign.',
                ]);
            }

            if ($current->employee_id === $newEmployeeId) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Aset sudah dipegang karyawan tersebut.',
                ]);
            }

            $this->return($current, $date, 'Ditransfer ke karyawan lain');

            return $this->assign($asset->fresh(), $newEmployeeId, $date, $notes);
        });
    }
}
```

## 7. Riwayat Aset (Detail Aset)

Halaman `assets/{asset}` menampilkan:

1. **Info aset**: kode, jenis, merek, MAC, IP, spesifikasi, status.
2. **Pemegang saat ini**: nama karyawan, NIP, departemen, tanggal assign, durasi (mis. "45 hari").
3. **Tabel riwayat** (urut terbaru di atas):

| Karyawan | Departemen | Tanggal Assign | Tanggal Return | Durasi | Catatan |
| --- | --- | --- | --- | --- | --- |
| Budi | RnD | 01 Jan 2026 | 31 Mar 2026 | 89 hari | Charger lengkap |
| Andi | EDP | 01 Apr 2026 | — (aktif) | 12 hari | — |

4. **Timeline visual** (opsional) dengan Alpine.js.

## 8. Aturan Bisnis Ringkas

| No | Aturan |
| --- | --- |
| R1 | Assign hanya untuk aset `Available`. |
| R2 | Satu aset maksimal satu assignment aktif — dijaga service + partial unique index. |
| R3 | Return wajib mengisi `returned_date` dan mengubah status aset ke `Available`. |
| R4 | `returned_date` tidak boleh lebih awal dari `assigned_date` (juga dijaga CHECK constraint). |
| R5 | Riwayat **tidak boleh dihapus** (append-only). Koreksi dilakukan dengan Return lalu Assign baru. |
| R6 | Assign/Return tidak boleh mengubah `mac_address` dan `asset_code`. |
| R7 | Menghapus karyawan yang masih memegang aset → ditolak. |
| R8 | Transfer aset antar karyawan = Return + Assign (dua baris riwayat), bukan update baris lama. |
| R9 | Transfer dieksekusi dalam **satu transaksi**; bila salah satu langkah gagal, tidak ada perubahan tersimpan. |
| R10 | Transfer hanya untuk aset `Assigned`; aset `Available` harus lewat Assign. |
| R11 | Karyawan tujuan tidak boleh sama dengan pemegang saat ini. |
| R12 | `Return` tetap tersedia sebagai aksi mandiri (aset kembali ke IT tanpa penerima langsung). |

## 9. Skenario Uji Utama (dari Acceptance Criteria)

**Skenario A — Assign pertama**

1. Aset `PC-2026-0001` status `Available`.
2. Admin assign ke "Budi" (RnD), tanggal 1 Jan 2026.
3. Ekspektasi: status aset = `Assigned`; tabel riwayat berisi Budi dengan `returned_date = NULL`.

**Skenario B — Pindah tangan Budi → Andi (via Return lalu Assign)**

1. Admin melakukan Return atas assignment Budi, tanggal 31 Mar 2026.
2. Ekspektasi: baris Budi punya `returned_date = 31 Mar 2026`; status aset kembali `Available`.
3. Admin assign ke "Andi" (EDP), tanggal 1 Apr 2026.
4. Ekspektasi: dua baris riwayat (Budi & Andi); pemegang saat ini = Andi; status = `Assigned`.

**Skenario B2 — Pindah tangan Budi → Andi (via Transfer, satu klik)**

1. Aset sedang dipegang Budi.
2. Admin membuka detail aset → klik **Transfer** → pilih Andi, isi tanggal `01 Apr 2026`.
3. Ekspektasi **identik** dengan Skenario B, tetapi hanya melalui satu form.
4. Tidak ada status aset yang sempat menjadi `Available` di antara kedua langkah (satu transaksi).

**Skenario C — Assign ganda ditolak**

1. Aset sedang dipegang Andi (`returned_date IS NULL`).
2. Admin mencoba assign ke karyawan lain.
3. Ekspektasi: pesan error "Aset masih terpasang pada karyawan lain", status tidak berubah, tidak ada baris riwayat baru.

**Skenario D — Transfer ke karyawan yang sama ditolak**

1. Aset dipegang Budi.
2. Admin memilih Budi sebagai karyawan tujuan.
3. Ekspektasi: error "Aset sudah dipegang karyawan tersebut."; tidak ada perubahan data.

**Skenario E — Transfer dibatalkan di tengah jalan**

1. Aset dipegang Budi.
2. Admin memilih karyawan tujuan yang kemudian gagal validasi.
3. Ekspektasi: **tidak ada** baris Budi yang ter-return; seluruh transaksi di-rollback.

## 10. Acceptance Terkait

- [x] Assign mengubah status menjadi `Assigned` dan mencatat baris riwayat.
- [x] Return mengubah status menjadi `Available` dan mengisi `returned_date`.
- [x] Setelah Budi → Andi, riwayat menampilkan keduanya dan pemegang saat ini adalah Andi.
- [x] Assign pada aset yang sudah terpakai ditolak dengan pesan jelas.
- [x] Assign pada aset `In Repair` / `Retired` ditolak.
- [x] Transfer selesai dalam **satu form** dan menghasilkan **dua** baris riwayat.
- [x] Transfer ke karyawan tujuan yang sama ditolak.
- [x] Transfer yang gagal tidak meninggalkan perubahan parsial.

### Catatan Implementasi

| Topik | Keputusan |
| --- | --- |
| Form Return | Panel inline di halaman detail aset (tidak ada route GET terpisah). |
| Transfer | `return()` + `assign()` dipanggil di dalam satu transaksi; hasilnya 2 baris riwayat. |
| Status aset | Setelah Return selalu menjadi `Available`. Bila perlu diperbaiki, admin mengubah status ke `In Repair` secara terpisah. |
| `assigned_by` | Diisi dari `auth()->id()` pada service. |
| View | Tombol Assign/Transfer/Return hanya tampil untuk admin. |
| Riwayat di detail aset | Dipaginasi 25 baris (parameter `?history=`) agar aset yang sering berpindah tangan tidak memuat seluruh riwayat sekaligus. |
