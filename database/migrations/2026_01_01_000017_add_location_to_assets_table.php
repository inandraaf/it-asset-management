<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lokasi fisik aset CCTV (mis. "Lobby", "Gudang", "Parkiran").
 *
 * CCTV tidak melekat pada departemen (T1) dan sering dipasang di titik yang
 * tidak terikat meja karyawan, sehingga lokasinya perlu dicatat eksplisit.
 * Nullable: hanya dipakai jenis CCTV, dan boleh diisi belakangan.
 *
 * @see dokumentasi/15-feedback-dan-tindak-lanjut.md X2
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('location', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }
};
