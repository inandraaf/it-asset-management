<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Merek & model menjadi OPSIONAL.
 *
 * PC banyak yang rakitan sendiri, sehingga merek tidak selalu diketahui.
 * Bila kosong, tampilan menampilkan "Rakitan".
 *
 * @see dokumentasi/15-feedback-dan-tindak-lanjut.md T4
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE assets ALTER COLUMN brand DROP NOT NULL');

        // Isi merek kosong dengan "Rakitan" agar data lama tetap terbaca.
        DB::statement("UPDATE assets SET brand = 'Rakitan' WHERE brand IS NULL OR TRIM(brand) = ''");
    }

    public function down(): void
    {
        DB::statement("UPDATE assets SET brand = 'Tidak diketahui' WHERE brand IS NULL");

        DB::statement('ALTER TABLE assets ALTER COLUMN brand SET NOT NULL');
    }
};
