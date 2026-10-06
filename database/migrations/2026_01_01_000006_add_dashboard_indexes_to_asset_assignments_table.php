<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index pendukung dashboard.
 *
 * - (employee_id, returned_date): mempercepat withCount assignment per
 *   departemen (relasi hasManyThrough Department::assignments) dan query
 *   "aset aktif per karyawan".
 * - (assigned_date, id): mempercepat daftar "alokasi terbaru" (ORDER BY
 *   assigned_date DESC, id DESC LIMIT 10) tanpa harus mengurutkan seluruh
 *   riwayat. B-tree dapat dipindai mundur untuk kebutuhan DESC.
 *
 * @see dokumentasi/08-dashboard.md §6
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_assignments', function (Blueprint $table) {
            $table->index(['employee_id', 'returned_date'], 'asset_assignments_employee_returned_index');
            $table->index(['assigned_date', 'id'], 'asset_assignments_assigned_date_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('asset_assignments', function (Blueprint $table) {
            $table->dropIndex('asset_assignments_employee_returned_index');
            $table->dropIndex('asset_assignments_assigned_date_id_index');
        });
    }
};
