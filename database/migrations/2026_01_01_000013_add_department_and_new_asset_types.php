<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dukung jenis aset CCTV & Printer (FB-4).
 *
 * - `type` CHECK diperluas.
 * - `mac_address` menjadi NULLABLE: Printer tidak punya MAC, CCTV opsional.
 * - `department_id` baru: aset departemen (CCTV/Printer) melekat pada departemen.
 *
 * @see dokumentasi/15-feedback-dan-tindak-lanjut.md FB-4
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Perluas CHECK constraint type.
        DB::statement('ALTER TABLE assets DROP CONSTRAINT IF EXISTS assets_type_check');
        DB::statement("ALTER TABLE assets ADD CONSTRAINT assets_type_check
            CHECK (type IN ('PC','Laptop','CCTV','Printer'))");

        // 2. MAC Address menjadi opsional (Printer tidak punya MAC).
        DB::statement('ALTER TABLE assets ALTER COLUMN mac_address DROP NOT NULL');

        // 3. Kolom departemen untuk aset yang melekat pada departemen.
        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('department_id')
                ->nullable()
                ->after('hostname')
                ->constrained('departments')
                ->nullOnDelete();

            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });

        // Hapus dulu aset jenis baru, lalu kembalikan constraint semula.
        DB::statement("DELETE FROM assets WHERE type IN ('CCTV','Printer')");
        DB::statement('ALTER TABLE assets DROP CONSTRAINT IF EXISTS assets_type_check');
        DB::statement("ALTER TABLE assets ADD CONSTRAINT assets_type_check
            CHECK (type IN ('PC','Laptop'))");

        DB::statement('ALTER TABLE assets ALTER COLUMN mac_address SET NOT NULL');
    }
};
