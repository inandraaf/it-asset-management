<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log maintenance per aset (append-only).
 *
 * Catatan:
 * - Tidak pakai soft delete — log riwayat tidak seharusnya dihapus.
 * - Tidak ada unique constraint; boleh ada lebih dari satu maintenance
 *   per aset per hari, atau zero maintenance pada hari tertentu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->date('performed_at');
            $table->text('description')->nullable();
            $table->decimal('cost', 12, 2)->default(0);
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('asset_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_maintenances');
    }
};
