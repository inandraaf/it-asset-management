<?php

namespace App\Http\Requests\Component;

use App\Enums\ComponentCategory;

/**
 * Logika bersama untuk normalisasi & validasi `specs` komponen.
 *
 * Key `specs` bergantung pada kategori (lihat ComponentCategory::specKeys()),
 * sehingga aturan validasi diturunkan dari enum, bukan daftar ganda.
 *
 * @see dokumentasi/14-manajemen-komponen.md §3.1
 */
trait NormalizesComponentSpecs
{
    /**
     * Baca kategori dari input (string) menjadi enum, bila valid.
     */
    protected function categoryFromInput(): ?ComponentCategory
    {
        $value = $this->input('category');

        return is_string($value) ? ComponentCategory::tryFrom($value) : null;
    }

    /**
     * Aturan validasi untuk setiap key `specs` kategori terpilih.
     *
     * @return array<string, array<int, string>>
     */
    protected function specRules(?ComponentCategory $category): array
    {
        if ($category === null) {
            return [];
        }

        $rules = [];

        foreach ($category->specKeys() as $key) {
            $rules["specs.$key"] = ['nullable', 'string', 'max:100'];
        }

        return $rules;
    }

    /**
     * Ambil hanya key `specs` yang relevan & terisi untuk kategori terpilih.
     *
     * @return array<string, string>
     */
    protected function normalizedSpecs(?ComponentCategory $category): array
    {
        if ($category === null) {
            return [];
        }

        $input = (array) $this->input('specs', []);
        $clean = [];

        foreach ($category->specKeys() as $key) {
            $value = $input[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $clean[$key] = trim($value);
            }
        }

        return $clean;
    }

    /**
     * Normalisasi umum: trim teks, ubah string kosong menjadi null.
     */
    protected function prepareComponentForValidation(): void
    {
        $nullable = fn ($v) => ($v === null || trim((string) $v) === '') ? null : trim((string) $v);

        $this->merge([
            'brand' => trim((string) $this->input('brand')),
            'model' => $nullable($this->input('model')),
            'serial_number' => $nullable($this->input('serial_number')),
            'notes' => $nullable($this->input('notes')),
            'specs' => $this->normalizedSpecs($this->categoryFromInput()),
        ]);
    }
}
