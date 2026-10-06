<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Memvalidasi format MAC Address.
 *
 * Format yang diterima hanya AA:BB:CC:DD:EE:FF (dipisah titik dua).
 * Format bertitik (AABB.CCDD.EEFF) dan tanpa pemisah ditolak.
 *
 * @see dokumentasi/10-validasi.md §4
 */
class MacAddress implements ValidationRule
{
    /** MAC yang secara teknis valid tetapi tidak masuk akal untuk inventaris. */
    private const FORBIDDEN = [
        '00:00:00:00:00:00',
        'FF:FF:FF:FF:FF:FF',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', strtoupper($value))) {
            $fail('Format MAC Address harus AA:BB:CC:DD:EE:FF.');

            return;
        }

        if (in_array(strtoupper($value), self::FORBIDDEN, true)) {
            $fail('MAC Address tersebut tidak valid untuk perangkat.');
        }
    }
}
