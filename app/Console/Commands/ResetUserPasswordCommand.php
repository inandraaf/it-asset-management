<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Reset kata sandi akun tanpa jalur email.
 *
 * Karena sistem ini hanya dipakai Admin IT dan tidak ada fitur lupa sandi
 * (FB-1), operator dengan akses server dapat mengatur ulang kata sandi
 * lewat perintah ini.
 *
 * @see dokumentasi/15-feedback-dan-tindak-lanjut.md FB-1
 */
class ResetUserPasswordCommand extends Command
{
    protected $signature = 'user:reset-password
                            {username : Username akun}
                            {--password= : Kata sandi baru (bila kosong, ditanyakan interaktif)}
                            {--generate : Buat kata sandi acak yang kuat}';

    protected $description = 'Atur ulang kata sandi akun (pengganti fitur lupa sandi)';

    public function handle(): int
    {
        $username = strtolower((string) $this->argument('username'));
        $user = User::where('username', $username)->first();

        if (! $user) {
            $this->error("Akun dengan username [{$username}] tidak ditemukan.");

            $daftar = User::pluck('username')->implode(', ');
            $this->line('Username tersedia: '.($daftar !== '' ? $daftar : '(belum ada akun)'));

            return self::FAILURE;
        }

        $password = (string) $this->option('password');

        if ($this->option('generate')) {
            $password = Str::password(16);
        } elseif ($password === '') {
            $password = (string) $this->secret('Kata sandi baru (minimal 8 karakter)');
            $konfirmasi = (string) $this->secret('Ulangi kata sandi baru');

            if ($password !== $konfirmasi) {
                $this->error('Konfirmasi kata sandi tidak cocok.');

                return self::FAILURE;
            }
        }

        // Validasi kekuatan kata sandi memakai aturan Laravel.
        $validator = validator(
            ['password' => $password],
            ['password' => ['required', Password::defaults()]]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user->forceFill(['password' => Hash::make($password)])->save();

        $this->info("Kata sandi akun [{$username}] berhasil diubah.");

        if ($this->option('generate')) {
            $this->line("Kata sandi baru: {$password}");
            $this->comment('Catat sekarang — nilai ini tidak ditampilkan lagi.');
        }

        return self::SUCCESS;
    }
}
