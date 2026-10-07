<?php

namespace App\Enums;

/**
 * Status komponen (part).
 *
 * `Installed` hanya boleh diubah oleh proses pasang/lepas
 * (lihat ComponentAllocationService), tidak diedit manual.
 *
 * @see dokumentasi/14-manajemen-komponen.md §6
 */
enum ComponentStatus: string
{
    case InStock = 'In Stock';
    case Installed = 'Installed';
    case InRepair = 'In Repair';
    case Retired = 'Retired';

    /**
     * Label tampilan Bahasa Indonesia (nilai enum tetap Inggris).
     *
     * @see dokumentasi/15-feedback-dan-tindak-lanjut.md FB-2
     */
    public function label(): string
    {
        return match ($this) {
            self::InStock => 'Di Gudang',
            self::Installed => 'Terpasang',
            self::InRepair => 'Diperbaiki',
            self::Retired => 'Dipensiunkan',
        };
    }

    /**
     * Apakah komponen dalam status ini boleh dipasang ke host.
     *
     * @see dokumentasi/14-manajemen-komponen.md K2, K3
     */
    public function isInstallable(): bool
    {
        return $this === self::InStock;
    }

    /**
     * Badge classes untuk tampilan (dipakai komponen Blade).
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::InStock => 'bg-emerald-100 text-emerald-700 ring-emerald-600/20',
            self::Installed => 'bg-blue-100 text-blue-700 ring-blue-600/20',
            self::InRepair => 'bg-amber-100 text-amber-700 ring-amber-600/20',
            self::Retired => 'bg-slate-100 text-slate-600 ring-slate-500/20',
        };
    }

    public function dotClass(): string
    {
        return match ($this) {
            self::InStock => 'bg-emerald-500',
            self::Installed => 'bg-blue-500',
            self::InRepair => 'bg-amber-500',
            self::Retired => 'bg-slate-400',
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
}
