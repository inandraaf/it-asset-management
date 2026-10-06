<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah kolom hostname (nama komputer di jaringan/Windows).
 *
 * Hostname dibuat kolom tersendiri, bukan bagian `specs`, karena sering dipakai
 * untuk mengenali dan mencari PC. Disimpan lowercase dan unik antar aset aktif.
 *
 * `specs` sendiri (jsonb) TIDAK diubah strukturnya: komponen baru (GPU,
 * motherboard, PSU, casing, monitor, dsb) cukup ditambahkan sebagai key baru
 * di dalam JSON, sehingga tidak perlu migrasi lagi.
 *
 * @see dokumentasi/03-database.md
 * @see dokumentasi/06-manajemen-aset.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('hostname', 63)->nullable()->after('brand');
        });

        // Unik antar aset AKTIF (partial, konsisten dengan unique index lain).
        // PostgreSQL mengizinkan banyak NULL, jadi aset tanpa hostname aman.
        DB::statement('CREATE UNIQUE INDEX assets_hostname_unique
            ON assets (hostname) WHERE deleted_at IS NULL AND hostname IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS assets_hostname_unique');

        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('hostname');
        });
    }
};
