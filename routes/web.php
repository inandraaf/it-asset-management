<?php

use App\Http\Controllers\AssetAssignmentController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\ComponentController;
use App\Http\Controllers\ComponentInstallationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', fn () => redirect()->route('dashboard'));

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    // Baca: admin + viewer.
    // whereNumber mencegah /employees/create tertangkap sebagai /employees/{employee}.
    Route::resource('departments', DepartmentController::class)
        ->only(['index'])
        ->whereNumber('department');
    Route::resource('employees', EmployeeController::class)
        ->only(['index', 'show'])
        ->whereNumber('employee');

    Route::get('assets', [AssetController::class, 'index'])->name('assets.index');
    Route::get('assets/{asset}', [AssetController::class, 'show'])
        ->whereNumber('asset')
        ->name('assets.show');

    // Komponen: baca untuk semua role
    Route::get('components', [ComponentController::class, 'index'])->name('components.index');
    Route::get('components/{component}', [ComponentController::class, 'show'])
        ->whereNumber('component')->name('components.show');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    // Tulis: hanya admin
    Route::resource('departments', DepartmentController::class)
        ->except(['index', 'show'])
        ->whereNumber('department');
    Route::resource('employees', EmployeeController::class)
        ->except(['index', 'show'])
        ->whereNumber('employee');

    // Aset: route statis (create, trashed) didahulukan agar tidak tertangkap
    // oleh {asset}, dan whereNumber menjadi pengaman kedua.
    Route::get('assets/create', [AssetController::class, 'create'])->name('assets.create');
    Route::post('assets', [AssetController::class, 'store'])->name('assets.store');
    Route::get('assets/trashed', [AssetController::class, 'trashed'])->name('assets.trashed');
    Route::get('assets/{asset}/edit', [AssetController::class, 'edit'])
        ->whereNumber('asset')->name('assets.edit');
    Route::put('assets/{asset}', [AssetController::class, 'update'])
        ->whereNumber('asset')->name('assets.update');
    Route::delete('assets/{asset}', [AssetController::class, 'destroy'])
        ->whereNumber('asset')->name('assets.destroy');
    Route::post('assets/{id}/restore', [AssetController::class, 'restore'])
        ->whereNumber('id')->name('assets.restore');
    Route::delete('assets/{id}/force-delete', [AssetController::class, 'forceDelete'])
        ->whereNumber('id')->name('assets.force-delete');

    // Alokasi aset: assign, return, transfer
    Route::get('assets/{asset}/assign', [AssetAssignmentController::class, 'create'])
        ->whereNumber('asset')->name('assets.assign.create');
    Route::post('assets/{asset}/assign', [AssetAssignmentController::class, 'store'])
        ->whereNumber('asset')->name('assets.assign.store');
    Route::post('assignments/{assignment}/return', [AssetAssignmentController::class, 'return'])
        ->whereNumber('assignment')->name('assignments.return');
    Route::get('assets/{asset}/transfer', [AssetAssignmentController::class, 'transferCreate'])
        ->whereNumber('asset')->name('assets.transfer.create');
    Route::post('assets/{asset}/transfer', [AssetAssignmentController::class, 'transfer'])
        ->whereNumber('asset')->name('assets.transfer.store');

    // Komponen: route statis (create, trashed) didahulukan, plus whereNumber.
    Route::get('components/create', [ComponentController::class, 'create'])->name('components.create');
    Route::post('components', [ComponentController::class, 'store'])->name('components.store');
    Route::get('components/trashed', [ComponentController::class, 'trashed'])->name('components.trashed');
    Route::get('components/{component}/edit', [ComponentController::class, 'edit'])
        ->whereNumber('component')->name('components.edit');
    Route::put('components/{component}', [ComponentController::class, 'update'])
        ->whereNumber('component')->name('components.update');
    Route::delete('components/{component}', [ComponentController::class, 'destroy'])
        ->whereNumber('component')->name('components.destroy');
    Route::post('components/{id}/restore', [ComponentController::class, 'restore'])
        ->whereNumber('id')->name('components.restore');

    // Pemasangan & pelepasan komponen
    Route::get('assets/{asset}/components/install', [ComponentInstallationController::class, 'create'])
        ->whereNumber('asset')->name('components.install.create');
    Route::post('assets/{asset}/components/install', [ComponentInstallationController::class, 'store'])
        ->whereNumber('asset')->name('components.install.store');
    Route::post('installations/{installation}/remove', [ComponentInstallationController::class, 'remove'])
        ->whereNumber('installation')->name('components.remove');
    Route::get('components/{component}/move', [ComponentInstallationController::class, 'moveCreate'])
        ->whereNumber('component')->name('components.move.create');
    Route::post('components/{component}/move', [ComponentInstallationController::class, 'move'])
        ->whereNumber('component')->name('components.move.store');

    // Pemasangan dari sisi komponen (pilih host)
    Route::get('components/{component}/install', [ComponentInstallationController::class, 'attachCreate'])
        ->whereNumber('component')->name('components.attach.create');
    Route::post('components/{component}/install', [ComponentInstallationController::class, 'attach'])
        ->whereNumber('component')->name('components.attach.store');
});

require __DIR__.'/auth.php';
