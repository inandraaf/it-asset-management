<?php

namespace Database\Seeders;

use App\Enums\ComponentCategory;
use App\Enums\ComponentStatus;
use App\Models\Component;
use App\Models\User;
use App\Services\CodeGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Data komponen (part) contoh untuk development.
 *
 * Kode komponen dibuat lewat CodeGenerator (bukan ditulis manual) agar sama
 * dengan alur aplikasi. Idempoten: kunci alami `serial_number`.
 *
 * Pemasangan ke host dilakukan di langkah terpisah (lihat ComponentInstallSeeder)
 * agar tidak menduplikasi logika service.
 *
 * @see dokumentasi/14-manajemen-komponen.md
 */
class ComponentSeeder extends Seeder
{
    /**
     * [category, brand, model, serial, specs]
     */
    public const COMPONENTS = [
        // CPU
        ['cpu', 'Intel', 'Core i7-11700', 'SN-CPU-0001', ['series' => 'i7-11700', 'cores' => '8', 'threads' => '16']],
        ['cpu', 'Intel', 'Core i5-10400', 'SN-CPU-0002', ['series' => 'i5-10400', 'cores' => '6', 'threads' => '12']],
        ['cpu', 'AMD', 'Ryzen 5 5600', 'SN-CPU-0003', ['series' => 'Ryzen 5 5600', 'cores' => '6', 'threads' => '12']],

        // RAM
        ['ram', 'Kingston', 'Fury Beast DDR4', 'SN-RAM-0001', ['capacity' => '8GB', 'type' => 'DDR4', 'speed' => '3200MHz', 'module' => 'DIMM']],
        ['ram', 'Kingston', 'Fury Beast DDR4', 'SN-RAM-0002', ['capacity' => '8GB', 'type' => 'DDR4', 'speed' => '3200MHz', 'module' => 'DIMM']],
        ['ram', 'Corsair', 'Vengeance LPX', 'SN-RAM-0003', ['capacity' => '16GB', 'type' => 'DDR4', 'speed' => '3200MHz', 'module' => 'DIMM']],
        ['ram', 'Samsung', 'SODIMM DDR4', 'SN-RAM-0004', ['capacity' => '8GB', 'type' => 'DDR4', 'speed' => '2666MHz', 'module' => 'SODIMM']],
        ['ram', 'Samsung', 'SODIMM DDR4', 'SN-RAM-0005', ['capacity' => '16GB', 'type' => 'DDR4', 'speed' => '3200MHz', 'module' => 'SODIMM']],

        // Storage
        ['storage', 'Samsung', '970 EVO Plus', 'SN-DSK-0001', ['capacity' => '1TB', 'type' => 'SSD', 'interface' => 'NVMe']],
        ['storage', 'Samsung', '970 EVO Plus', 'SN-DSK-0002', ['capacity' => '512GB', 'type' => 'SSD', 'interface' => 'NVMe']],
        ['storage', 'Seagate', 'Barracuda', 'SN-DSK-0003', ['capacity' => '1TB', 'type' => 'HDD', 'interface' => 'SATA']],
        ['storage', 'Western Digital', 'Blue SN570', 'SN-DSK-0004', ['capacity' => '512GB', 'type' => 'SSD', 'interface' => 'NVMe']],
        ['storage', 'Seagate', 'Barracuda', 'SN-DSK-0005', ['capacity' => '2TB', 'type' => 'HDD', 'interface' => 'SATA']],

        // GPU
        ['gpu', 'NVIDIA', 'GeForce GTX 1650', 'SN-GPU-0001', ['model' => 'GTX 1650', 'memory' => '4GB', 'memory_type' => 'GDDR6']],
        ['gpu', 'NVIDIA', 'GeForce RTX 3060', 'SN-GPU-0002', ['model' => 'RTX 3060', 'memory' => '12GB', 'memory_type' => 'GDDR6']],

        // Motherboard
        ['motherboard', 'ASUS', 'Prime H510M-E', 'SN-MBD-0001', ['chipset' => 'H510', 'socket' => 'LGA1200', 'form_factor' => 'micro-ATX']],
        ['motherboard', 'Gigabyte', 'B560M DS3H', 'SN-MBD-0002', ['chipset' => 'B560', 'socket' => 'LGA1200', 'form_factor' => 'micro-ATX']],

        // PSU
        ['psu', 'Corsair', 'CV550', 'SN-PSU-0001', ['wattage' => '550W', 'efficiency' => '80+ Bronze', 'modular' => 'Non-modular']],
        ['psu', 'Seasonic', 'Focus GX-650', 'SN-PSU-0002', ['wattage' => '650W', 'efficiency' => '80+ Gold', 'modular' => 'Full-modular']],

        // Casing
        ['casing', 'Cooler Master', 'MasterBox Q300L', 'SN-CSG-0001', ['form_factor' => 'micro-ATX', 'bay' => '1x3.5"']],

        // Peripheral
        ['monitor', 'Dell', 'P2419H', 'SN-MON-0001', ['size' => '24"', 'resolution' => '1920x1080', 'panel' => 'IPS']],
        ['monitor', 'LG', '27UL500', 'SN-MON-0002', ['size' => '27"', 'resolution' => '3840x2160', 'panel' => 'IPS']],
        ['monitor', 'HP', 'P22h G4', 'SN-MON-0003', ['size' => '21.5"', 'resolution' => '1920x1080', 'panel' => 'IPS']],
        ['keyboard', 'Logitech', 'K120', 'SN-KBD-0001', ['connection' => 'USB', 'layout' => 'QWERTY']],
        ['keyboard', 'Dell', 'KB216', 'SN-KBD-0002', ['connection' => 'USB', 'layout' => 'QWERTY']],
        ['mouse', 'Logitech', 'B100', 'SN-MSE-0001', ['connection' => 'USB', 'dpi' => '1000']],
        ['mouse', 'Dell', 'MS116', 'SN-MSE-0002', ['connection' => 'USB', 'dpi' => '1000']],
    ];

    /**
     * Pemasangan contoh: [serial komponen, hostname host, tanggal pasang].
     *
     * Dipasang lewat ComponentAllocationService agar konsisten dengan alur
     * aplikasi (status & riwayat ikut terjaga).
     */
    private const INSTALLATIONS = [
        // PC-RND-01: rakitan lengkap (2x8GB = total 16GB).
        ['SN-MBD-0001', 'pc-rnd-01', '-45 days'],
        ['SN-CPU-0001', 'pc-rnd-01', '-45 days'],
        ['SN-RAM-0001', 'pc-rnd-01', '-45 days'],
        ['SN-RAM-0002', 'pc-rnd-01', '-45 days'],
        ['SN-DSK-0001', 'pc-rnd-01', '-45 days'],

        // PC-RND-02: 1x16GB + SSD + HDD (dua tipe storage).
        ['SN-MBD-0002', 'pc-rnd-02', '-40 days'],
        ['SN-CPU-0002', 'pc-rnd-02', '-40 days'],
        ['SN-RAM-0003', 'pc-rnd-02', '-40 days'],
        ['SN-DSK-0002', 'pc-rnd-02', '-40 days'],
        ['SN-DSK-0005', 'pc-rnd-02', '-40 days'],
        ['SN-GPU-0002', 'pc-rnd-02', '-40 days'],
        ['SN-RAM-0004', 'pc-edp-01', '-35 days'],
        ['SN-DSK-0004', 'pc-edp-01', '-35 days'],
        ['SN-MON-0001', 'pc-edp-01', '-35 days'],
        ['SN-KBD-0001', 'pc-edp-01', '-35 days'],
        ['SN-MSE-0001', 'pc-edp-01', '-35 days'],
        ['SN-CPU-0003', 'lt-rnd-01', '-30 days'],
        ['SN-RAM-0005', 'lt-rnd-01', '-30 days'],
        ['SN-DSK-0003', 'lt-edp-01', '-30 days'],
        ['SN-MON-0002', 'pc-rnd-02', '-20 days'],
        ['SN-MON-0003', 'pc-hrga-01', '-20 days'],
        ['SN-KBD-0002', 'pc-edp-02', '-20 days'],
        ['SN-MSE-0002', 'pc-edp-02', '-20 days'],
        ['SN-GPU-0001', 'pc-ep-01', '-15 days'],
        ['SN-MBD-0002', 'pc-cc-01', '-10 days'],
        ['SN-PSU-0002', 'pc-cc-01', '-10 days'],
        ['SN-CSG-0001', 'pc-cc-01', '-10 days'],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('ComponentSeeder dilewati di production (data contoh).');

            return;
        }

        $generator = app(CodeGenerator::class);
        $adminId = User::query()->value('id');

        foreach (self::COMPONENTS as [$category, $brand, $model, $serial, $specs]) {
            if (Component::where('serial_number', $serial)->exists()) {
                continue;
            }

            $enum = ComponentCategory::from($category);

            DB::transaction(fn () => Component::create([
                'component_code' => $generator->nextComponent($enum),
                'category' => $enum,
                'brand' => $brand,
                'model' => $model,
                'serial_number' => $serial,
                'specs' => $specs,
                'status' => ComponentStatus::InStock,
                'created_by' => $adminId,
            ]));
        }

        $this->seedInstallations($adminId);
    }

    /**
     * Pasang sebagian komponen ke host contoh.
     */
    private function seedInstallations(?int $adminId): void
    {
        $service = app(\App\Services\ComponentAllocationService::class);

        foreach (self::INSTALLATIONS as [$serial, $hostname, $when]) {
            $component = Component::where('serial_number', $serial)->first();
            $asset = \App\Models\Asset::where('hostname', strtolower($hostname))->first();

            if (! $component || ! $asset) {
                continue;
            }

            // Idempoten: lewati bila sudah pernah dipasang.
            if ($component->installations()->exists()) {
                continue;
            }

            $date = Carbon::parse($when);

            // Jaga aturan K11 bila host dibuat setelah tanggal contoh.
            $installDate = $date->lt($asset->created_at) ? $asset->created_at : $date;

            try {
                DB::transaction(function () use ($service, $component, $asset, $installDate, $adminId) {
                    auth()->loginUsingId($adminId);
                    $service->install($component, $asset, $installDate, 'Data contoh seeder.');
                });
            } catch (\Throwable $e) {
                $this->command?->warn("Gagal memasang {$serial} ke {$hostname}: ".$e->getMessage());
            }
        }
    }
}
