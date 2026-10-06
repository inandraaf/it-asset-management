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
 * Password diambil dari env agar tidak ada kredensial yang ter-hardcode:
 * - SEED_ADMIN_PASSWORD untuk akun admin (wajib di non-lokal).
 * - SEED_VIEWER_PASSWORD untuk akun viewer demo.
 *
 * Di production, bila SEED_VIEWER_PASSWORD kosong, akun viewer demo TIDAK
 * dibuat — mencegah akun dengan password bersama tetap hidup. Di lokal,
 * viewer memakai password admin demi kemudahan.
 *
 * Role tidak mass-assignable; pemberiannya lewat assignRole().
 *
 * @see dokumentasi/04-autentikasi.md §6
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminPassword = (string) config('itam.seed_admin_password');

        if ($adminPassword === '') {
            if (app()->isProduction()) {
                throw new \RuntimeException(
                    'SEED_ADMIN_PASSWORD wajib diisi di environment ini sebelum menjalankan seeder.'
                );
            }

            $adminPassword = Str::password(16);
            $this->command?->warn('SEED_ADMIN_PASSWORD kosong. Password acak admin: '.$adminPassword);
        }

        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin IT',
                'password' => Hash::make($adminPassword),
                'email_verified_at' => now(),
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

            // Lokal: pakai password admin agar mudah login saat development.
            $viewerPassword = $adminPassword;
        }

        $viewer = User::updateOrCreate(
            ['email' => 'viewer@example.com'],
            [
                'name' => 'Viewer Manager',
                'password' => Hash::make($viewerPassword),
                'email_verified_at' => now(),
            ]
        );
        $viewer->assignRole(UserRole::Viewer);
    }
}
