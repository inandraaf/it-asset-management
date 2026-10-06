<?php

namespace App\Services;

use App\Enums\AssetType;
use App\Models\Asset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Membuat kode aset berurutan dengan format {PREFIX}-{TAHUN}-{URUT 4 DIGIT}.
 *
 * WAJIB dipanggil di dalam transaksi database. Penguncian memakai
 * pg_advisory_xact_lock (PostgreSQL) yang otomatis dilepas saat transaksi
 * selesai. Ini mencegah dua request bersamaan menghasilkan nomor urut yang
 * sama — sesuatu yang tidak bisa dijamin oleh lockForUpdate pada baris yang
 * belum ada (phantom insert).
 *
 * @see dokumentasi/06-manajemen-aset.md §2
 */
class AssetCodeGenerator
{
    public function next(AssetType $type): string
    {
        $prefix = $type->codePrefix();
        $year = now()->year;

        DB::select('SELECT pg_advisory_xact_lock(?)', [$this->lockKey($prefix, $year)]);

        // Urutkan berdasarkan NILAI numerik bagian terakhir, bukan teksnya.
        // Secara teks "PC-2026-9999" > "PC-2026-10000", sehingga urutan
        // leksikografis akan salah begitu urutan melewati 4 digit dan
        // menghasilkan kode yang sudah terpakai.
        $last = Asset::withTrashed()
            ->where('asset_code', 'like', $prefix.'-'.$year.'-%')
            ->orderByRaw("CAST(SPLIT_PART(asset_code, '-', 3) AS INTEGER) DESC")
            ->value('asset_code');

        $sequence = $last ? ((int) Str::afterLast($last, '-')) + 1 : 1;

        return sprintf('%s-%d-%04d', $prefix, $year, $sequence);
    }

    private function lockKey(string $prefix, int $year): int
    {
        return crc32('asset_code:'.$prefix.':'.$year);
    }
}
