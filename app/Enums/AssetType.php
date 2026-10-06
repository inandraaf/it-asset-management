<?php

namespace App\Enums;

enum AssetType: string
{
    case PC = 'PC';
    case Laptop = 'Laptop';

    /**
     * Prefix yang dipakai saat generate kode aset.
     */
    public function codePrefix(): string
    {
        return match ($this) {
            self::PC => 'PC',
            self::Laptop => 'LT',
        };
    }

    /**
     * Label untuk tampilan.
     */
    public function label(): string
    {
        return $this->value;
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
