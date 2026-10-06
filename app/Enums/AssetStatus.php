<?php

namespace App\Enums;

enum AssetStatus: string
{
    case Available = 'Available';
    case Assigned = 'Assigned';
    case InRepair = 'In Repair';
    case Retired = 'Retired';

    public function label(): string
    {
        return $this->value;
    }

    /**
     * Apakah aset dalam status ini boleh di-assign ke karyawan.
     *
     * @see dokumentasi/07-alokasi-aset.md R1
     */
    public function isAssignable(): bool
    {
        return $this === self::Available;
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
}
