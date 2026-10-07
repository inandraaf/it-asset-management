<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute Autentikasi
|--------------------------------------------------------------------------
|
| Sistem internal ini praktis dipakai satu akun Admin IT, sehingga:
|
| - Login memakai USERNAME (bukan email).
| - Registrasi publik dimatikan.
| - TIDAK ada lupa sandi / reset via email / verifikasi email.
|   Bila Admin IT lupa kata sandi, operator menjalankan:
|       php artisan user:reset-password {username}
|
| @see dokumentasi/04-autentikasi.md
| @see dokumentasi/15-feedback-dan-tindak-lanjut.md FB-1
|
*/

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    // Ganti kata sandi sendiri (setelah login).
    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
