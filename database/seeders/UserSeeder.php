<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Akun awal untuk sistem internal.
 *
 * Login memakai USERNAME (bukan email — FB-1). Password diambil dari env
 * SEED_ADMIN_PASSWORD agar tidak ada kredensial ter-hardcode.
 *
 * Role tidak mass-assignable; pemberiannya lewat assignRole().
 *
 * @see dokumentasi/04-autentikasi.md §6
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) config('itam.seed_admin_password');

        if ($password === '') {
            if (app()->isProduction()) {
                throw new \RuntimeException(
                    'SEED_ADMIN_PASSWORD wajib diisi di environment ini sebelum menjalankan seeder.'
                );
            }

            $password = Str::password(16);
            $this->command?->warn('SEED_ADMIN_PASSWORD kosong. Password acak admin: '.$password);
        }

        $admin = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Admin IT',
                'password' => Hash::make($password),
            ]
        );
        $admin->assignRole(UserRole::Admin);

        $viewerPassword = (string) config('itam.seed_viewer_password');

        if ($viewerPassword === '') {
            if (app()->isProduction()) {
                $this->command?->warn(
                    'SEED_VIEWER_PASSWORD kosong; akun viewer demo tidak dibuat di environment ini.'
                );

                return;
            }

            $viewerPassword = $password;
        }

        $viewer = User::updateOrCreate(
            ['username' => 'andi.edp'],
            [
                'name' => 'Andi Kurniawan',
                'password' => Hash::make($viewerPassword),
            ]
        );
        $viewer->assignRole(UserRole::Viewer);
    }
}
