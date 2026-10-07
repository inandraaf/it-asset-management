<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom `capacity_mb` (angka) pada komponen.
 *
 * Kapasitas disimpan sebagai teks di `specs.capacity` (mis. "8GB", "512GB",
 * "1TB") sehingga **tidak bisa dijumlahkan** di SQL. Kolom ini menyimpan
 * nilainya dalam MB agar:
 * - filter RAM dapat memakai **akumulasi total** (1x16GB sama dengan 2x8GB);
 * - filter kapasitas lain lebih mudah dan cepat (ter-index).
 *
 * Diisi otomatis oleh model Component saat menyimpan.
 *
 * @see dokumentasi/15-feedback-dan-tindak-lanjut.md R2/R3
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('components', function (Blueprint $table) {
            $table->unsignedBigInteger('capacity_mb')->nullable()->after('specs');
            $table->index('capacity_mb');
        });

        // Isi data lama dari specs.capacity.
        foreach (DB::table('components')->whereNotNull('specs')->get() as $row) {
            $specs = json_decode($row->specs, true) ?: [];
            $capacity = $specs['capacity'] ?? null;

            if (! is_string($capacity) || trim($capacity) === '') {
                continue;
            }

            if (! preg_match('/^\s*([\d.]+)\s*(MB|GB|TB)\s*$/i', $capacity, $m)) {
                continue;
            }

            $num = (float) $m[1];
            $mb = match (strtoupper($m[2])) {
                'MB' => $num,
                'GB' => $num * 1024,
                'TB' => $num * 1024 * 1024,
            };

            DB::table('components')->where('id', $row->id)->update(['capacity_mb' => (int) round($mb)]);
        }
    }

    public function down(): void
    {
        Schema::table('components', function (Blueprint $table) {
            $table->dropIndex(['capacity_mb']);
            $table->dropColumn('capacity_mb');
        });
    }
};
