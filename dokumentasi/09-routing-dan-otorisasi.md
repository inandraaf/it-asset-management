# 09 — Routing & Otorisasi

## 1. Ringkasan Route

| Method | URI | Nama | Controller | Middleware |
| --- | --- | --- | --- | --- |
| GET | `/` | — | redirect ke `dashboard` | — |
| GET | `/dashboard` | `dashboard` | `DashboardController` | `auth`, `verified` |
| GET | `/departments` | `departments.index` | `DepartmentController@index` | `auth` |
| GET | `/departments/create` | `departments.create` | `DepartmentController@create` | `auth`, `role:admin` |
| POST | `/departments` | `departments.store` | `DepartmentController@store` | `auth`, `role:admin` |
| GET | `/departments/{id}/edit` | `departments.edit` | `DepartmentController@edit` | `auth`, `role:admin` |
| PUT | `/departments/{id}` | `departments.update` | `DepartmentController@update` | `auth`, `role:admin` |
| DELETE | `/departments/{id}` | `departments.destroy` | `DepartmentController@destroy` | `auth`, `role:admin` |
| GET | `/employees` | `employees.index` | `EmployeeController@index` | `auth` |
| GET | `/employees/create` | `employees.create` | `EmployeeController@create` | `auth`, `role:admin` |
| POST | `/employees` | `employees.store` | `EmployeeController@store` | `auth`, `role:admin` |
| GET | `/employees/{id}` | `employees.show` | `EmployeeController@show` | `auth` |
| GET | `/employees/{id}/edit` | `employees.edit` | `EmployeeController@edit` | `auth`, `role:admin` |
| PUT | `/employees/{id}` | `employees.update` | `EmployeeController@update` | `auth`, `role:admin` |
| DELETE | `/employees/{id}` | `employees.destroy` | `EmployeeController@destroy` | `auth`, `role:admin` |
| GET | `/assets` | `assets.index` | `AssetController@index` | `auth` |
| GET | `/assets/create` | `assets.create` | `AssetController@create` | `auth`, `role:admin` |
| POST | `/assets` | `assets.store` | `AssetController@store` | `auth`, `role:admin` |
| GET | `/assets/{asset}` | `assets.show` | `AssetController@show` | `auth` |
| GET | `/assets/{asset}/edit` | `assets.edit` | `AssetController@edit` | `auth`, `role:admin` |
| PUT | `/assets/{asset}` | `assets.update` | `AssetController@update` | `auth`, `role:admin` |
| DELETE | `/assets/{asset}` | `assets.destroy` | `AssetController@destroy` | `auth`, `role:admin` |
| GET | `/assets/trashed` | `assets.trashed` | `AssetController@trashed` | `auth`, `role:admin` |
| POST | `/assets/{id}/restore` | `assets.restore` | `AssetController@restore` | `auth`, `role:admin` |
| DELETE | `/assets/{id}/force-delete` | `assets.force-delete` | `AssetController@forceDelete` | `auth`, `role:admin` |
| GET | `/assets/{asset}/assign` | `assets.assign.create` | `AssetAssignmentController@create` | `auth`, `role:admin` |
| POST | `/assets/{asset}/assign` | `assets.assign.store` | `AssetAssignmentController@store` | `auth`, `role:admin` |
| POST | `/assignments/{assignment}/return` | `assignments.return` | `AssetAssignmentController@return` | `auth`, `role:admin` |
| GET | `/assets/{asset}/transfer` | `assets.transfer.create` | `AssetAssignmentController@transferCreate` | `auth`, `role:admin` |
| POST | `/assets/{asset}/transfer` | `assets.transfer.store` | `AssetAssignmentController@transfer` | `auth`, `role:admin` |

## 2. Implementasi `routes/web.php`

```php
<?php

use App\Http\Controllers\AssetAssignmentController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Gunakan redirect()->route(), BUKAN Route::redirect('/', '/dashboard').
// Route::redirect menyimpan path literal sehingga kehilangan base path
// saat aplikasi diakses lewat subfolder (mis. /inven_it/public/).
Route::get('/', fn () => redirect()->route('dashboard'));

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    // Baca: admin + viewer
    Route::resource('departments', DepartmentController::class)->only(['index']);
    Route::resource('employees', EmployeeController::class)->only(['index', 'show']);
    Route::resource('assets', AssetController::class)->only(['index', 'show']);

    // Profil
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    // Tulis: hanya admin
    Route::resource('departments', DepartmentController::class)->except(['index', 'show']);
    Route::resource('employees', EmployeeController::class)->except(['index', 'show']);
    Route::resource('assets', AssetController::class)->except(['index', 'show']);

    Route::get('assets/{asset}/assign', [AssetAssignmentController::class, 'create'])->name('assets.assign.create');
    Route::post('assets/{asset}/assign', [AssetAssignmentController::class, 'store'])->name('assets.assign.store');
    Route::post('assignments/{assignment}/return', [AssetAssignmentController::class, 'return'])->name('assignments.return');
    Route::get('assets/{asset}/transfer', [AssetAssignmentController::class, 'transferCreate'])->name('assets.transfer.create');
    Route::post('assets/{asset}/transfer', [AssetAssignmentController::class, 'transfer'])->name('assets.transfer.store');
});

require __DIR__.'/auth.php';
```

### Catatan Penting Urutan Route

Laravel mengevaluasi route berdasarkan urutan deklarasi. Alternatif lebih aman: pisahkan registry resource atau gunakan constraint angka pada parameter:

```php
Route::get('assets/{asset}', [AssetController::class, 'show'])
    ->whereNumber('asset')
    ->name('assets.show');
```

Dengan `whereNumber`, `/assets/create` tidak akan tertangkap oleh route `show` meskipun dideklarasikan lebih dulu.

## 3. Policy

Untuk MVP, middleware role sudah cukup. Gunakan Policy hanya bila muncul kebutuhan aturan per-record.

```bash
php artisan make:policy AssetPolicy --model=Asset
```

```php
// app/Policies/AssetPolicy.php
public function viewAny(User $user): bool   { return true; }
public function view(User $user, Asset $a): bool { return true; }
public function create(User $user): bool    { return $user->isAdmin(); }
public function update(User $user, Asset $a): bool
{
    return $user->isAdmin() && $a->status !== AssetStatus::Retired;
}
public function delete(User $user, Asset $a): bool
{
    return $user->isAdmin() && ! $a->activeAssignment()->exists();
}
```

## 4. Navigasi UI

`resources/views/layouts/navigation.blade.php` — tautan:

- Dashboard
- Aset
- Karyawan
- Departemen
- (hanya admin) tombol "Tambah Aset"

Sembunyikan tautan tulis untuk role `viewer` dengan:

```blade
@if (auth()->user()->isAdmin())
    <x-nav-link :href="route('assets.create')" :active="request()->routeIs('assets.create')">Tambah Aset</x-nav-link>
@endif
```

> Menyembunyikan tautan **bukan** pengamanan. Middleware role tetap wajib.

## 5. Flash Message

Gunakan konsisten untuk feedback:

| Aksi | Pesan |
| --- | --- |
| Simpan berhasil | `Aset berhasil ditambahkan.` |
| Update berhasil | `Data berhasil diperbarui.` |
| Hapus berhasil | `Data berhasil dihapus.` |
| Assign berhasil | `Aset {kode} ditugaskan ke {nama}.` |
| Return berhasil | `Aset {kode} telah dikembalikan ke IT.` |
| Transfer berhasil | `Aset {kode} ditransfer dari {asal} ke {tujuan}.` |

Diberikan lewat `->with('success', '...')` dan ditampilkan oleh komponen di layout.

## 6. Acceptance Terkait

- [ ] Semua route tulis mengembalikan `403` untuk viewer.
- [ ] Route baca dapat diakses viewer.
- [ ] `/assets/create` tidak tertangkap sebagai `show` dengan ID "create".
