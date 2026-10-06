<?php

namespace App\Services;

use App\Enums\AssetType;
use App\Enums\ComponentCategory;
use App\Models\Asset;
use App\Models\Component;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Membuat kode berurutan dengan format {PREFIX}-{TAHUN}-{URUT 4 DIGIT}.
 *
 * Dipakai untuk kode aset host (`PC-2026-0001`, `LT-2026-0001`) maupun kode
 * komponen (`RAM-2026-0001`, `DSK-2026-0002`), sehingga logika urutan hanya
 * ada di satu tempat.
 *
 * WAJIB dipanggil di dalam transaksi database. Penguncian memakai
 * pg_advisory_xact_lock (PostgreSQL) yang otomatis dilepas saat transaksi
 * selesai. Ini mencegah dua request bersamaan menghasilkan nomor urut yang
 * sama — sesuatu yang tidak bisa dijamin oleh lockForUpdate pada baris yang
 * belum ada (phantom insert).
 *
 * @see dokumentasi/06-manajemen-aset.md §2
 * @see dokumentasi/14-manajemen-komponen.md §5
 */
class CodeGenerator
{
    /**
     * Kode aset host berikutnya (PC/Laptop).
     *
     * Nama method `next()` dipertahankan dari AssetCodeGenerator lama agar
     * pemakaian di AssetController dan seeder tidak berubah.
     */
    public function next(AssetType $type): string
    {
        return $this->nextFor(Asset::class, Asset::CODE_COLUMN, $type->codePrefix());
    }

    /**
     * Kode komponen berikutnya; prefix mengikuti kategori.
     */
    public function nextComponent(ComponentCategory $category): string
    {
        return $this->nextFor(Component::class, Component::CODE_COLUMN, $category->codePrefix());
    }

    /**
     * Hasilkan kode berikutnya untuk sebuah model berkode.
     *
     * @param  class-string<Model>  $modelClass  Model dengan kolom kode (mis. Asset::class)
     * @param  string  $column  Nama kolom kode (mis. 'asset_code')
     * @param  string  $prefix  Prefix kode (mis. 'PC', 'RAM')
     */
    public function nextFor(string $modelClass, string $column, string $prefix): string
    {
        $prefix = strtoupper($prefix);
        $year = now()->year;

        /** @var Model $model */
        $model = new $modelClass;

        DB::select(
            'SELECT pg_advisory_xact_lock(?)',
            [$this->lockKey($model->getTable(), $column, $prefix, $year)]
        );

        // Urutkan berdasarkan NILAI numerik bagian terakhir, bukan teksnya.
        // Secara teks "PC-2026-9999" > "PC-2026-10000", sehingga urutan
        // leksikografis akan salah begitu urutan melewati 4 digit dan
        // menghasilkan kode yang sudah terpakai.
        $last = $modelClass::withTrashed()
            ->where($column, 'like', $prefix.'-'.$year.'-%')
            ->orderByRaw(sprintf(
                "CAST(SPLIT_PART(%s, '-', 3) AS INTEGER) DESC",
                $column
            ))
            ->value($column);

        $sequence = $last ? ((int) Str::afterLast($last, '-')) + 1 : 1;

        return sprintf('%s-%d-%04d', $prefix, $year, $sequence);
    }

    /**
     * Kunci per (tabel, kolom, prefix, tahun) agar dua deret berbeda tidak
     * saling memblokir.
     */
    private function lockKey(string $table, string $column, string $prefix, int $year): int
    {
        return crc32("{$table}.{$column}:{$prefix}:{$year}");
    }
}
