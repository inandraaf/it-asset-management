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
     * Field **esensial** per kategori — yang benar-benar dipakai untuk
     * pelacakan & pencarian cepat.
     *
     * Sengaja dibuat sesedikit mungkin (1–2 field) agar perakitan tidak
     * melelahkan. Field teknis lainnya ada di `advancedSpecKeys()`.
     *
     * Karena `specs` bertipe jsonb, daftar ini bisa berubah tanpa migrasi.
     *
     * @see dokumentasi/15-feedback-dan-tindak-lanjut.md FB-5
     *
     * @return array<int, string>
     */
    public function specKeys(): array
    {
        return match ($this) {
            self::Cpu => ['series'],
            self::Ram => ['capacity', 'type'],
            self::Storage => ['capacity', 'type'],
            self::Gpu => ['model'],
            self::Motherboard => ['chipset'],
            self::Psu => ['wattage'],
            self::Casing => ['form_factor'],
            self::Monitor => ['size'],
            self::Keyboard => ['connection'],
            self::Mouse => ['connection'],
            self::Other => [],
        };
    }

    /**
     * Field **lanjutan** (opsional) per kategori.
     *
     * Disembunyikan di balik panel "Spesifikasi lanjutan" pada form, sehingga
     * alur normal hanya mengisi field esensial.
     *
     * @return array<int, string>
     */
    public function advancedSpecKeys(): array
    {
        return match ($this) {
            self::Cpu => ['cores', 'threads', 'socket', 'base_clock'],
            self::Ram => ['speed', 'module'],
            self::Storage => ['interface', 'rpm'],
            self::Gpu => ['memory', 'memory_type', 'interface'],
            self::Motherboard => ['socket', 'form_factor'],
            self::Psu => ['efficiency', 'modular'],
            self::Casing => ['bay'],
            self::Monitor => ['resolution', 'panel'],
            self::Keyboard => ['layout'],
            self::Mouse => ['dpi'],
            self::Other => [],
        };
    }

    /**
     * Seluruh key yang dikenal kategori ini (esensial + lanjutan).
     *
     * @return array<int, string>
     */
    public function allSpecKeys(): array
    {
        return [...$this->specKeys(), ...$this->advancedSpecKeys()];
    }

    /**
     * Key `specs` yang memakai **dropdown pilihan** (bukan teks bebas),
     * beserta daftar nilainya.
     *
     * Nilai-nilai ini cukup baku sehingga dropdown mencegah salah ketik.
     * Key yang tidak terdaftar di sini tetap memakai input teks.
     *
     * @see dokumentasi/15-feedback-dan-tindak-lanjut.md S4
     *
     * @return array<string, array<int, string>>
     */
    public static function selectOptions(): array
    {
        return [
            // RAM
            'capacity' => ['4GB', '8GB', '16GB', '32GB', '64GB', '128GB'],
            'type' => ['DDR3', 'DDR4', 'DDR5'],
            'module' => ['DIMM', 'SODIMM'],

            // Storage
            'storage_capacity' => ['120GB', '128GB', '240GB', '256GB', '512GB', '1TB', '2TB', '4TB', '8TB'],
            'storage_type' => ['SSD', 'HDD', 'NVMe'],

            // Power Supply
            'wattage' => ['300W', '380W', '400W', '450W', '500W', '550W', '600W', '650W', '750W', '850W', '1000W'],
            'efficiency' => ['80+ White', '80+ Bronze', '80+ Silver', '80+ Gold', '80+ Platinum'],
            'modular' => ['Modular', 'Semi-modular', 'Non-modular'],

            // Monitor
            'size' => ['19"', '20"', '21.5"', '22"', '24"', '27"', '32"'],
            'resolution' => ['1366x768', '1600x900', '1920x1080', '2560x1440', '3840x2160'],
            'panel' => ['TN', 'IPS', 'VA', 'OLED'],

            // Peripheral
            'connection' => ['USB', 'Wireless', 'Bluetooth', 'PS/2'],
        ];
    }

    /**
     * Daftar pilihan untuk sebuah key `specs`, bila memakai dropdown.
     *
     * @return array<int, string>
     */
    public static function optionsFor(string $key, ?self $category = null): array
    {
        $options = self::selectOptions();

        // 'capacity'/'type' dipakai RAM & Storage dengan nilai berbeda.
        if ($key === 'capacity') {
            return match ($category) {
                self::Storage => $options['storage_capacity'],
                default => $options['capacity'],
            };
        }

        if ($key === 'type') {
            return match ($category) {
                self::Storage => $options['storage_type'],
                default => $options['type'],
            };
        }

        return $options[$key] ?? [];
    }

    /**
     * Placeholder untuk tiap key `specs` kategori ini.
     *
     * @return array<string, string>
     */
    public function specPlaceholders(): array
    {
        $all = [
            'series' => 'Contoh: i7-11700 atau Ryzen 5 5600',
            'socket' => 'Contoh: LGA1200',
            'cores' => 'Contoh: 6',
            'threads' => 'Contoh: 12',
            'base_clock' => 'Contoh: 2.9GHz',
            'capacity' => 'Contoh: 8GB / 512GB',
            'type' => 'Contoh: DDR4 / SSD',
            'speed' => 'Contoh: 3200MHz',
            'module' => 'Contoh: DIMM / SODIMM',
            'interface' => 'Contoh: NVMe / PCIe 4.0 x16',
            'rpm' => 'Contoh: 7200',
            'model' => 'Contoh: RTX 3060',
            'memory' => 'Contoh: 12GB',
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

        foreach ($this->allSpecKeys() as $key) {
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
            'series' => 'Seri CPU',
            'socket' => 'Socket',
            'cores' => 'Jumlah Core',
            'threads' => 'Jumlah Thread',
            'base_clock' => 'Clock Dasar',
            'capacity' => 'Kapasitas',
            'type' => 'Tipe',
            'speed' => 'Kecepatan',
            'module' => 'Bentuk Modul',
            'interface' => 'Antarmuka',
            'rpm' => 'RPM',
            'model' => 'Model',
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
