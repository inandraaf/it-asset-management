<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat pemasangan komponen pada host (PC/Laptop).
 *
 * Satu baris = satu periode pemasangan. Invariant: satu komponen maksimal
 * terpasang di satu host pada satu waktu (partial unique index).
 *
 * Desain: dokumentasi/14-manajemen-komponen.md §4.2
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('component_installations', function (Blueprint $table) {
            $table->id();
            // Komponen adalah subjek riwayat: bila komponen hilang, riwayatnya
            // tidak bermakna lagi. Namun penghapusan permanen tetap dicegah di
            // level aplikasi bila komponen punya riwayat (audit).
            $table->foreignId('component_id')->constrained('components')->cascadeOnDelete();
            // Host adalah konteks: kode PC harus tetap terekam, sehingga PC yang
            // punya riwayat komponen tidak boleh di-hard-delete.
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->date('installed_date');
            $table->date('removed_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('installed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['asset_id', 'removed_date']);
            $table->index(['component_id', 'removed_date']);
        });

        DB::statement('CREATE UNIQUE INDEX one_active_install_per_component
            ON component_installations (component_id) WHERE removed_date IS NULL');

        DB::statement('ALTER TABLE component_installations ADD CONSTRAINT component_install_date_check
            CHECK (removed_date IS NULL OR removed_date >= installed_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('component_installations');
    }
};
