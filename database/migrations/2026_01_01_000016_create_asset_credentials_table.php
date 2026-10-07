<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kredensial per aset, jumlah bebas.
 *
 * Satu PC biasanya punya lebih dari satu akun (mis. Admin + User Biasa),
 * sehingga kredensial dipisah dari tabel `assets` ke tabel tersendiri.
 *
 * Kata sandi disimpan TERENKRIPSI (cast `encrypted` pada model), memakai APP_KEY.
 *
 * @see dokumentasi/15-feedback-dan-tindak-lanjut.md S5
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('label', 100);            // mis. "Admin", "User Biasa", "VNC"
            $table->string('username', 150)->nullable();
            $table->text('password')->nullable();    // terenkripsi
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('asset_id');
        });

        // Pindahkan kredensial lama (kolom assets) menjadi baris tabel baru.
        $rows = DB::table('assets')
            ->where(fn ($q) => $q
                ->whereNotNull('windows_username')
                ->orWhereNotNull('windows_password')
                ->orWhereNotNull('vnc_password')
                ->orWhereNotNull('remote_notes'))
            ->get();

        foreach ($rows as $asset) {
            if ($asset->windows_username || $asset->windows_password) {
                DB::table('asset_credentials')->insert([
                    'asset_id' => $asset->id,
                    'label' => 'Windows',
                    'username' => $asset->windows_username,
                    'password' => $asset->windows_password,
                    'notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($asset->vnc_password || $asset->remote_notes) {
                DB::table('asset_credentials')->insert([
                    'asset_id' => $asset->id,
                    'label' => 'VNC',
                    'username' => null,
                    'password' => $asset->vnc_password,
                    'notes' => $asset->remote_notes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Kolom lama tidak dipakai lagi.
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['windows_username', 'windows_password', 'vnc_password', 'remote_notes']);
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('windows_username', 100)->nullable();
            $table->text('windows_password')->nullable();
            $table->text('vnc_password')->nullable();
            $table->text('remote_notes')->nullable();
        });

        Schema::dropIfExists('asset_credentials');
    }
};
