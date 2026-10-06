<?php

namespace App\Console\Commands;

use App\Enums\ComponentCategory;
use App\Models\Asset;
use App\Models\Component;
use App\Services\CodeGenerator;
use App\Services\ComponentAllocationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Impor part dari `assets.specs` lama menjadi baris `components`.
 *
 * Data lama tidak dapat dipisah menjadi keping fisik: nilai seperti
 * "16GB (2x8GB) DDR4" tidak memberi tahu ada berapa keping dan nomor serinya.
 * Karena itu perintah ini membuat **satu** komponen per key, menandainya di
 * `notes` sebagai "perlu diverifikasi", dan melewati key `os`.
 *
 * Gunakan --dry-run lebih dulu. Lihat dokumentasi/14-manajemen-komponen.md §10.
 */
class ImportComponentsFromSpecsCommand extends Command
{
    protected $signature = 'components:import-from-specs
                            {--dry-run : Tampilkan rencana tanpa menulis ke database}
                            {--install : Sekaligus pasang komponen ke host asalnya}
                            {--force : Lanjutkan walau status aset bukan Available}';

    protected $description = 'Impor komponen dari assets.specs lama (satu komponen per key, perlu verifikasi manual)';

    /** Key `specs` lama yang dipetakan ke kategori komponen. */
    private const KEY_TO_CATEGORY = [
        'cpu' => 'cpu',
        'ram' => 'ram',
        'storage' => 'storage',
        'storage_2' => 'storage',
        'gpu' => 'gpu',
        'motherboard' => 'motherboard',
        'psu' => 'psu',
        'casing' => 'casing',
        'monitor' => 'monitor',
        'keyboard' => 'keyboard',
        'mouse' => 'mouse',
    ];

    public function handle(CodeGenerator $generator, ComponentAllocationService $allocation): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $withInstall = (bool) $this->option('install');
        $adminId = \App\Models\User::query()->value('id');

        $assets = Asset::query()->get();
        $planned = 0;
        $created = 0;
        $installed = 0;
        $skipped = 0;

        foreach ($assets as $asset) {
            $specs = $asset->specs ?? [];

            foreach (self::KEY_TO_CATEGORY as $key => $categoryValue) {
                $value = $specs[$key] ?? null;

                if (! is_string($value) || trim($value) === '') {
                    continue;
                }

                $planned++;

                if ($dryRun) {
                    $this->line(sprintf(
                        '  [dry-run] %s + %s "%s"',
                        $asset->asset_code,
                        $categoryValue,
                        trim($value)
                    ));

                    continue;
                }

                $category = ComponentCategory::from($categoryValue);

                $component = DB::transaction(fn () => Component::create([
                    'component_code' => $generator->nextComponent($category),
                    'category' => $category,
                    'brand' => trim($value),
                    'model' => null,
                    'serial_number' => null,
                    'specs' => [],
                    'status' => \App\Enums\ComponentStatus::InStock,
                    'notes' => 'Hasil impor dari specs aset '.$asset->asset_code.'. PERLU DIVERIFIKASI: data lama tidak memuat jumlah keping maupun nomor seri.',
                    'created_by' => $adminId,
                ]));

                $created++;

                if ($withInstall) {
                    try {
                        auth()->loginUsingId($adminId);
                        $allocation->install($component, $asset, Carbon::now(), 'Hasil impor.');
                        $installed++;
                    } catch (\Throwable $e) {
                        $skipped++;
                        $this->warn("  Gagal memasang {$component->component_code}: ".$e->getMessage());
                    }
                }
            }
        }

        $this->newLine();
        $this->info($dryRun
            ? "Rencana impor: {$planned} komponen (dry-run, tidak ada perubahan)."
            : "Selesai: {$created} komponen dibuat, {$installed} terpasang, {$skipped} dilewati.");

        if (! $dryRun && $created > 0) {
            $this->comment('Langkah berikutnya: verifikasi tiap komponen (jumlah keping & nomor seri) lalu sesuaikan statusnya.');
        }

        return self::SUCCESS;
    }
}
