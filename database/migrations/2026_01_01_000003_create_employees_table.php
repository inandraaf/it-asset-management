<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel master karyawan.
 *
 * FK department_id memakai restrictOnDelete agar departemen yang masih
 * memiliki karyawan tidak dapat dihapus.
 *
 * @see dokumentasi/03-database.md §3
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('nip', 30)->unique();
            $table->string('nama', 150);
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->timestamps();

            $table->index('nama');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
