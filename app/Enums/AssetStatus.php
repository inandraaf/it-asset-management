<?php

namespace App\Enums;

enum AssetStatus: string
{
    case Available = 'Available';
    case Assigned = 'Assigned';
    case InRepair = 'In Repair';
    case Retired = 'Retired';

    /**
     * Label tampilan Bahasa Indonesia.
     *
     * Nilai enum tetap Inggris (dipakai di database & CHECK constraint),
     * hanya label tampilan yang diterjemahkan.
     *
     * @see dokumentasi/15-feedback-dan-tindak-lanjut.md FB-2
     */
    public function label(): string
    {
        return match ($this) {
            self::Available => 'Tersedia',
            self::Assigned => 'Terpakai',
            self::InRepair => 'Diperbaiki',
            self::Retired => 'Dipensiunkan',
        };
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
