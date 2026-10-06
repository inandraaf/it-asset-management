<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel komponen (part) sebagai aset tersendiri.
 *
 * Desain: dokumentasi/14-manajemen-komponen.md §4.1
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('components', function (Blueprint $table) {
            $table->id();
            $table->string('component_code', 30);
            $table->string('category', 20);
            $table->string('brand', 100);
            $table->string('model', 150)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->jsonb('specs')->default('{}');
            $table->string('status', 20)->default('In Stock');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('category');
            $table->index('status');
        });

        // Unique parsial: hanya antar baris aktif (belum di-soft delete).
        DB::statement('CREATE UNIQUE INDEX components_component_code_unique
            ON components (component_code) WHERE deleted_at IS NULL');

        DB::statement('CREATE UNIQUE INDEX components_serial_number_unique
            ON components (serial_number) WHERE deleted_at IS NULL AND serial_number IS NOT NULL');

        DB::statement("ALTER TABLE components ADD CONSTRAINT components_category_check
            CHECK (category IN ('cpu','ram','storage','gpu','motherboard','psu','casing','monitor','keyboard','mouse','other'))");

        DB::statement("ALTER TABLE components ADD CONSTRAINT components_status_check
            CHECK (status IN ('In Stock','Installed','In Repair','Retired'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('components');
    }
};
