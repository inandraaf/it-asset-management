<?php

namespace App\Enums;

/**
 * Kategori komponen (part) komputer.
 *
 * - **Internal**: dipasang di dalam host (PC/Laptop).
 * - **Peripheral**: terhubung ke host dan bisa berpindah sendiri.
 *
 * OS tidak termasuk kategori karena bukan benda fisik — OS tetap atribut host.
 *
 * @see dokumentasi/14-manajemen-komponen.md §3
 */
enum ComponentCategory: string
{
    case Cpu = 'cpu';
    case Ram = 'ram';
    case Storage = 'storage';
    case Gpu = 'gpu';
    case Motherboard = 'motherboard';
    case Psu = 'psu';
    case Casing = 'casing';
    case Monitor = 'monitor';
    case Keyboard = 'keyboard';
    case Mouse = 'mouse';
    case Other = 'other';

    /**
     * Prefix kode komponen, contoh: RAM-2026-0001.
     */
    public function codePrefix(): string
    {
        return match ($this) {
            self::Cpu => 'CPU',
            self::Ram => 'RAM',
            self::Storage => 'DSK',
            self::Gpu => 'GPU',
            self::Motherboard => 'MBD',
            self::Psu => 'PSU',
            self::Casing => 'CSG',
            self::Monitor => 'MON',
            self::Keyboard => 'KBD',
            self::Mouse => 'MSE',
            self::Other => 'CMP',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Cpu => 'CPU',
            self::Ram => 'RAM',
            self::Storage => 'Storage',
            self::Gpu => 'GPU',
            self::Motherboard => 'Motherboard',
            self::Psu => 'Power Supply',
            self::Casing => 'Casing',
            self::Monitor => 'Monitor',
            self::Keyboard => 'Keyboard',
            self::Mouse => 'Mouse',
            self::Other => 'Lainnya',
        };
    }

    /**
     * Part yang dipasang di dalam host.
     */
    public function isInternal(): bool
    {
        return in_array($this, [
            self::Cpu, self::Ram, self::Storage, self::Gpu,
            self::Motherboard, self::Psu, self::Casing,
        ], true);
    }

    public function isPeripheral(): bool
    {
        return in_array($this, [
            self::Monitor, self::Keyboard, self::Mouse,
        ], true);
    }

    /**
     * Key `specs` yang relevan untuk kategori ini (acuan tampilan form).
     *
     * Karena `specs` bertipe jsonb, daftar ini bisa berubah tanpa migrasi.
     *
     * @return array<int, string>
     */
    public function specKeys(): array
    {
        return match ($this) {
            self::Cpu => ['socket', 'cores', 'threads', 'base_clock'],
            self::Ram => ['capacity', 'type', 'speed', 'module'],
            self::Storage => ['capacity', 'type', 'interface'],
            self::Gpu => ['memory', 'memory_type', 'interface'],
            self::Motherboard => ['socket', 'form_factor', 'chipset'],
            self::Psu => ['wattage', 'efficiency', 'modular'],
            self::Casing => ['form_factor', 'bay'],
            self::Monitor => ['size', 'resolution', 'panel'],
            self::Keyboard => ['connection', 'layout'],
            self::Mouse => ['connection', 'dpi'],
            self::Other => [],
        };
    }

    /**
     * Placeholder untuk tiap key `specs` kategori ini.
     *
     * @return array<string, string>
     */
    public function specPlaceholders(): array
    {
        $all = [
            'socket' => 'Contoh: LGA1200',
            'cores' => 'Contoh: 6',
            'threads' => 'Contoh: 12',
            'base_clock' => 'Contoh: 2.9GHz',
            'capacity' => 'Contoh: 8GB',
            'type' => 'Contoh: DDR4 / SSD',
            'speed' => 'Contoh: 3200MHz',
            'module' => 'Contoh: DIMM / SODIMM',
            'interface' => 'Contoh: NVMe / PCIe 4.0 x16',
            'memory' => 'Contoh: 4GB',
            'memory_type' => 'Contoh: GDDR6',
            'form_factor' => 'Contoh: micro-ATX',
            'chipset' => 'Contoh: H510',
            'wattage' => 'Contoh: 500W',
            'efficiency' => 'Contoh: 80+ Bronze',
            'modular' => 'Contoh: Non-modular',
            'bay' => 'Contoh: 2x3.5"',
            'size' => 'Contoh: 24"',
            'resolution' => 'Contoh: 1920x1080',
            'panel' => 'Contoh: IPS',
            'connection' => 'Contoh: USB / Wireless',
            'layout' => 'Contoh: QWERTY',
            'dpi' => 'Contoh: 1000',
        ];

        $out = [];

        foreach ($this->specKeys() as $key) {
            $out[$key] = $all[$key] ?? '';
        }

        return $out;
    }

    /**
     * Label manusiawi untuk key `specs`.
     */
    public static function specLabel(string $key): string
    {
        return match ($key) {
            'socket' => 'Socket',
            'cores' => 'Jumlah Core',
            'threads' => 'Jumlah Thread',
            'base_clock' => 'Clock Dasar',
            'capacity' => 'Kapasitas',
            'type' => 'Tipe',
            'speed' => 'Kecepatan',
            'module' => 'Bentuk Modul',
            'interface' => 'Antarmuka',
            'memory' => 'Memori',
            'memory_type' => 'Tipe Memori',
            'form_factor' => 'Form Factor',
            'chipset' => 'Chipset',
            'wattage' => 'Daya',
            'efficiency' => 'Efisiensi',
            'modular' => 'Modular',
            'bay' => 'Bay',
            'size' => 'Ukuran',
            'resolution' => 'Resolusi',
            'panel' => 'Panel',
            'connection' => 'Koneksi',
            'layout' => 'Layout',
            'dpi' => 'DPI',
            default => ucfirst(str_replace('_', ' ', $key)),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }

    /**
     * Opsi dikelompokkan Internal / Peripheral, untuk dropdown ber-optgroup.
     *
     * @return array<string, array<string, string>>
     */
    public static function groupedOptions(): array
    {
        $groups = ['Internal' => [], 'Peripheral' => [], 'Lainnya' => []];

        foreach (self::cases() as $case) {
            $group = match (true) {
                $case->isInternal() => 'Internal',
                $case->isPeripheral() => 'Peripheral',
                default => 'Lainnya',
            };

            $groups[$group][$case->value] = $case->label();
        }

        return array_filter($groups);
    }
}
