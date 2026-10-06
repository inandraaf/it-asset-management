<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah kolom role pada users.
 *
 * Default sengaja 'viewer' (least privilege) agar akun baru tidak otomatis
 * mendapat hak Admin. Role admin hanya diberikan lewat jalur terkontrol
 * (seeder berbasis env, atau aksi khusus admin) — lihat dokumentasi/04-autentikasi.md.
 *
 * @see dokumentasi/03-database.md §6 dan §7
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('viewer')->after('password');
            $table->index('role');
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check
            CHECK (role IN ('admin','viewer'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
