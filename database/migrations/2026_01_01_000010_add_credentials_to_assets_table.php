<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom kredensial & akses remote per aset.
 *
 * `windows_password` dan `vnc_password` disimpan TERENKRIPSI (cast `encrypted`
 * pada model Asset, memakai APP_KEY). Jangan pernah menyimpan sebagai teks biasa.
 *
 * Kolom tipe `text` (bukan varchar) karena ciphertext jauh lebih panjang
 * daripada plaintext-nya.
 *
 * @see dokumentasi/15-feedback-dan-tindak-lanjut.md FB-8
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('windows_username', 100)->nullable()->after('specs');
            $table->text('windows_password')->nullable()->after('windows_username');
            $table->text('vnc_password')->nullable()->after('windows_password');
            $table->text('remote_notes')->nullable()->after('vnc_password');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['windows_username', 'windows_password', 'vnc_password', 'remote_notes']);
        });
    }
};
