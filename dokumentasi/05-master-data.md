# 05 — Master Data (Departemen & Karyawan)

## 1. Modul Departemen

### Field

| Field | Tipe | Wajib | Aturan |
| --- | --- | --- | --- |
| `nama_dept` | string(100) | Ya | Unik, 2–100 karakter |

### Data Awal

`EDP`, `HRGA`, `EP`, `RnD`, `CC`.

### Route

```php
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::resource('departments', DepartmentController::class)->except('show');
});
```

| Method | URI | Nama | Aksi |
| --- | --- | --- | --- |
| GET | `/departments` | `departments.index` | Daftar + pencarian |
| GET | `/departments/create` | `departments.create` | Form tambah |
| POST | `/departments` | `departments.store` | Simpan |
| GET | `/departments/{department}/edit` | `departments.edit` | Form edit |
| PUT/PATCH | `/departments/{department}` | `departments.update` | Update |
| DELETE | `/departments/{department}` | `departments.destroy` | Hapus |

### Aturan Bisnis

- Nama departemen unik **case-insensitive** (`"RnD"` == `"rnd"`). Normalisasi `trim` di `prepareForValidation`.
  - **Catatan implementasi:** rule `unique` bawaan Laravel selalu membandingkan kolom secara persis (case-sensitive) lalu di-AND dengan kondisi tambahan, sehingga **tidak bisa** dipakai untuk keunikan case-insensitive. Pengecekan dilakukan manual di `withValidator()` dengan `LOWER(nama_dept) = ?`.
- **Tidak boleh menghapus** departemen yang masih memiliki karyawan → kembalikan error, bukan 500. Cek `$department->employees()->exists()` lebih dulu. FK `restrictOnDelete` tetap menjadi jaring pengaman terakhir.
- Tampilkan jumlah karyawan per departemen pada halaman index (`withCount('employees')`).

### Model

```php
class Department extends Model
{
    protected $fillable = ['nama_dept'];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
```

---

## 2. Modul Karyawan

### Field

| Field | Tipe | Wajib | Aturan |
| --- | --- | --- | --- |
| `nip` | string(30) | Ya | Unik |
| `nama` | string(150) | Ya | 2–150 karakter |
| `department_id` | bigint | Ya | Harus ada di `departments` |

### Route

```php
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::resource('employees', EmployeeController::class);
});
```

| Method | URI | Nama |
| --- | --- | --- |
| GET | `/employees` | `employees.index` |
| GET | `/employees/create` | `employees.create` |
| POST | `/employees` | `employees.store` |
| GET | `/employees/{employee}` | `employees.show` |
| GET | `/employees/{employee}/edit` | `employees.edit` |
| PUT/PATCH | `/employees/{employee}` | `employees.update` |
| DELETE | `/employees/{employee}` | `employees.destroy` |

### Aturan Bisnis

- `nip` unik; saat update, kecualikan ID karyawan itu sendiri (`Rule::unique()->ignore()`).
- **Tidak boleh menghapus** karyawan yang masih memiliki aset aktif (`assignment returned_date IS NULL`). Pesan: "Karyawan masih memegang aset, tarik aset terlebih dahulu."
- **Tidak boleh menghapus** karyawan yang punya riwayat assignment (walau sudah di-return), karena FK `asset_assignments.employee_id` memakai `restrictOnDelete`. Pesan: "Karyawan memiliki riwayat pemakaian aset sehingga tidak dapat dihapus."
  - Alasan: mempertahankan nama pemegang di riwayat (audit). Alternatif "nonaktifkan" belum ada di MVP.
- Riwayat assignment karyawan **tetap disimpan** saat karyawan dihapus; karena itu penghapusan dibatasi seperti di atas.
- Halaman `show` karyawan menampilkan aset yang sedang dipegang + riwayat lengkap.

### Relasi Model

```php
class Employee extends Model
{
    protected $fillable = ['nip', 'nama', 'department_id'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

    public function activeAssets(): HasMany
    {
        return $this->hasMany(AssetAssignment::class)->whereNull('returned_date');
    }
}
```

---

## 3. Pencarian & Filter

- Departemen: cari berdasarkan `nama_dept`.
- Karyawan: cari berdasarkan `nama` atau `nip`; filter berdasarkan `department_id`.

```php
$employees = Employee::query()
    ->with('department')
    ->when($request->filled('q'), function ($q) use ($request) {
        $term = '%'.$request->string('q').'%';
        $q->where(fn ($w) => $w->where('nama', 'ilike', $term)
                                ->orWhere('nip', 'ilike', $term));
    })
    ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
    ->orderBy('nama')
    ->paginate(15)
    ->withQueryString();
```

> Gunakan operator `ilike` (PostgreSQL) agar pencarian tidak case-sensitive.

## 4. Halaman & Komponen View

| View | Isi |
| --- | --- |
| `departments/index.blade.php` | Tabel + tombol tambah/edit/hapus + pencarian |
| `departments/create.blade.php` | Form nama departemen |
| `departments/edit.blade.php` | Form edit |
| `employees/index.blade.php` | Tabel + filter departemen + pencarian |
| `employees/create.blade.php` | Form karyawan (dropdown departemen) |
| `employees/edit.blade.php` | Form edit |
| `employees/show.blade.php` | Profil + aset aktif + riwayat |

## 5. Acceptance Terkait

- [x] Admin dapat menambah, mengedit, menghapus departemen.
- [x] Admin tidak bisa menambah departemen dengan nama yang sama (termasuk beda huruf besar/kecil).
- [x] Admin dapat menambah karyawan "Budi" pada departemen "RnD".
- [x] Menghapus departemen yang masih punya karyawan ditolak dengan pesan jelas.
- [x] Menghapus karyawan yang masih memegang aset ditolak dengan pesan jelas.
- [x] Viewer hanya dapat melihat daftar (route tulis → 403).
