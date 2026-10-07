<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Login memakai USERNAME, bukan email.
 *
 * Sistem ini praktis hanya dipakai satu akun Admin IT, sehingga:
 * - `email` & `email_verified_at` dihapus (beserta jalur lupa sandi bawaan Breeze).
 * - `username` ditambahkan sebagai identitas login.
 *
 * Bila Admin IT lupa kata sandi, digunakan perintah `user:reset-password`.
 *
 * @see dokumentasi/15-feedback-dan-tindak-lanjut.md FB-1
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->after('name');
        });

        // Isi username dari bagian depan email yang ada, agar tidak null
        // (dilewati bila tabel kosong, mis. saat migrate:fresh).
        DB::statement("UPDATE users SET username = LOWER(SPLIT_PART(email, '@', 1)) WHERE username IS NULL");

        // Bila hasilnya bentrok (dua email dengan nama sama), tambahkan id.
        DB::statement("UPDATE users SET username = username || '-' || id
            WHERE id IN (
                SELECT id FROM (
                    SELECT id, ROW_NUMBER() OVER (PARTITION BY username ORDER BY id) AS rn
                    FROM users
                ) t WHERE t.rn > 1
            )");

        // Jadikan NOT NULL via raw statement (lebih pasti daripada ->change()).
        DB::statement('ALTER TABLE users ALTER COLUMN username SET NOT NULL');

        Schema::table('users', function (Blueprint $table) {
            $table->unique('username');
            $table->dropUnique(['email']);
            $table->dropColumn(['email', 'email_verified_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->after('name');
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });

        // Kembalikan email dari username sebagai perkiraan.
        DB::statement("UPDATE users SET email = username || '@example.com' WHERE email IS NULL");
        DB::statement('ALTER TABLE users ALTER COLUMN email SET NOT NULL');

        Schema::table('users', function (Blueprint $table) {
            $table->unique('email');
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
