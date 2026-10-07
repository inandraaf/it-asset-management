<?php

namespace App\Enums;

/**
 * Jenis aset.
 *
 * - **PC / Laptop**: aset komputer — melekat pada karyawan, punya komponen.
 * - **CCTV / Printer**: perangkat departemen — menempel pada departemen,
 *   boleh punya PIC karyawan, tidak punya komponen.
 *
 * @see dokumentasi/15-feedback-dan-tindak-lanjut.md FB-4
 */
enum AssetType: string
{
    case PC = 'PC';
    case Laptop = 'Laptop';
    case Cctv = 'CCTV';
    case Printer = 'Printer';

    /**
     * Prefix yang dipakai saat generate kode aset.
     */
    public function codePrefix(): string
    {
        return match ($this) {
            self::PC => 'PC',
            self::Laptop => 'LT',
            self::Cctv => 'CCTV',
            self::Printer => 'PRN',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PC => 'PC',
            self::Laptop => 'Laptop',
            self::Cctv => 'CCTV',
            self::Printer => 'Printer',
        };
    }

    /**
     * Aset komputer: pemiliknya karyawan dan bisa punya komponen.
     */
    public function isComputer(): bool
    {
        return in_array($this, [self::PC, self::Laptop], true);
    }

    /**
     * Apakah aset melekat pada sebuah departemen.
     *
     * Hanya **Printer**: penempatannya di meja/dekat karyawan sebuah departemen.
     * CCTV **tidak** melekat departemen — tanggung jawab Admin IT, tanpa pemilik.
     */
    public function requiresDepartment(): bool
    {
        return $this === self::Printer;
    }

    /**
     * Apakah aset dapat ditugaskan ke karyawan.
     *
     * Hanya PC/Laptop. Printer melekat departemen, CCTV tanpa pemilik.
     */
    public function isAssignable(): bool
    {
        return $this->isComputer();
    }

    /**
     * Perangkat departemen (bukan komputer). Dipakai untuk pengelompokan tampilan.
     */
    public function isDepartmentDevice(): bool
    {
        return ! $this->isComputer();
    }

    /**
     * Apakah jenis ini memerlukan MAC Address.
     *
     * - PC/Laptop: wajib
     * - CCTV: opsional
     * - Printer: tidak dipakai
     */
    public function requiresMac(): bool
    {
        return $this->isComputer();
    }

    /**
     * Apakah jenis ini boleh punya MAC Address sama sekali.
     */
    public function allowsMac(): bool
    {
        return $this !== self::Printer;
    }

    /**
     * Apakah jenis ini mendukung pemasangan komponen.
     */
    public function supportsComponents(): bool
    {
        return $this->isComputer();
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
     * Opsi dikelompokkan untuk dropdown ber-optgroup.
     *
     * @return array<string, array<string, string>>
     */
    public static function groupedOptions(): array
    {
        return [
            'Komputer' => [
                self::PC->value => self::PC->label(),
                self::Laptop->value => self::Laptop->label(),
            ],
            'Perangkat Departemen' => [
                self::Cctv->value => self::Cctv->label(),
                self::Printer->value => self::Printer->label(),
            ],
        ];
    }
}
