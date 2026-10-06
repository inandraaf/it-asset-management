<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Log riwayat alokasi aset (append-only).
 *
 * Invariant yang dijaga di level database:
 * - Satu aset hanya boleh punya maksimal satu assignment aktif
 *   (returned_date IS NULL) — dijaga partial unique index.
 * - returned_date tidak boleh lebih awal dari assigned_date — dijaga CHECK.
 *
 * FK employee_id memakai restrictOnDelete: karyawan yang punya riwayat aset
 * tidak dapat di-hard-delete, sehingga nama pemegang di riwayat tetap utuh.
 * Aturan bisnisnya ditegakkan di EmployeeController dengan pesan yang jelas.
 *
 * @see dokumentasi/03-database.md §5 dan §7
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->date('assigned_date');
            $table->date('returned_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['asset_id', 'returned_date']);
        });

        DB::statement('CREATE UNIQUE INDEX one_active_assignment_per_asset
            ON asset_assignments (asset_id) WHERE returned_date IS NULL');

        DB::statement('ALTER TABLE asset_assignments ADD CONSTRAINT assignment_date_check
            CHECK (returned_date IS NULL OR returned_date >= assigned_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_assignments');
    }
};
