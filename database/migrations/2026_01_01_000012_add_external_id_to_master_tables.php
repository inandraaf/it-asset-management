<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Persiapan sinkronisasi master data dari server (FB-7).
 *
 * Belum ada integrasi; hanya menyiapkan kunci idempoten agar sinkronisasi
 * berikutnya tidak menggandakan data.
 *
 * @see dokumentasi/15-feedback-dan-tindak-lanjut.md FB-7
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['departments', 'employees'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('external_id', 100)->nullable()->after('id');
                $blueprint->timestamp('synced_at')->nullable();
            });

            DB::statement("CREATE UNIQUE INDEX {$table}_external_id_unique
                ON {$table} (external_id) WHERE external_id IS NOT NULL");
        }
    }

    public function down(): void
    {
        foreach (['departments', 'employees'] as $table) {
            DB::statement("DROP INDEX IF EXISTS {$table}_external_id_unique");

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['external_id', 'synced_at']);
            });
        }
    }
};
