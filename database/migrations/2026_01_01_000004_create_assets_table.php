<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel aset (PC & Laptop).
 *
 * Catatan penting:
 * - Memakai soft delete, sehingga unique index untuk asset_code, mac_address,
 *   dan ip_address dibuat PARTIAL (WHERE deleted_at IS NULL) agar aset yang
 *   sudah dihapus tidak memblokir penggunaan ulang MAC/IP.
 * - PostgreSQL mengizinkan banyak NULL pada unique index, jadi ip_address
 *   opsional aman tanpa partial index terpisah (namun tetap dibuat eksplisit).
 *
 * @see dokumentasi/03-database.md §4 dan §7
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code', 30);
            $table->string('type', 10);
            $table->string('brand', 100);
            $table->string('mac_address', 17);
            $table->string('ip_address', 45)->nullable();
            $table->jsonb('specs')->default('{}');
            $table->string('status', 20)->default('Available');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('status');
            $table->index('type');
        });

        // Unique index partial: hanya berlaku untuk baris aktif (belum dihapus).
        DB::statement('CREATE UNIQUE INDEX assets_asset_code_unique
            ON assets (asset_code) WHERE deleted_at IS NULL');

        DB::statement('CREATE UNIQUE INDEX assets_mac_address_unique
            ON assets (mac_address) WHERE deleted_at IS NULL');

        DB::statement('CREATE UNIQUE INDEX assets_ip_address_unique
            ON assets (ip_address) WHERE deleted_at IS NULL AND ip_address IS NOT NULL');

        DB::statement("ALTER TABLE assets ADD CONSTRAINT assets_type_check
            CHECK (type IN ('PC','Laptop'))");

        DB::statement("ALTER TABLE assets ADD CONSTRAINT assets_status_check
            CHECK (status IN ('Available','Assigned','In Repair','Retired'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
