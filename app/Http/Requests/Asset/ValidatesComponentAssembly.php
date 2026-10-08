<?php

namespace App\Http\Requests\Asset;

use App\Enums\ComponentCategory;
use App\Models\Component;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Aturan validasi "rakit komponen" saat aset BARU dibuat (X3).
 *
 * Aset baru belum punya komponen, sehingga jalur "sudah ada di gudang" hanya
 * boleh memilih komponen berstatus In Stock & belum terpasang.
 *
 * Bentuk input:
 * - `components[new][k][category|brand|model|serial_number|specs][...]` — komponen pengadaan baru
 * - `components[stock][]` — id komponen gudang yang dipasang
 *
 * @see dokumentasi/15-feedback-dan-tindak-lanjut.md X3
 */
trait ValidatesComponentAssembly
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function componentAssemblyRules(): array
    {
        return [
            'components' => ['nullable', 'array'],
            'components.new' => ['nullable', 'array'],
            'components.new.*.category' => ['required', Rule::enum(ComponentCategory::class)],
            'components.new.*.brand' => ['required', 'string', 'min:1', 'max:100'],
            'components.new.*.model' => ['nullable', 'string', 'max:150'],
            'components.new.*.serial_number' => [
                'nullable', 'string', 'max:100',
                Rule::unique('components', 'serial_number')->whereNull('deleted_at'),
            ],
            'components.new.*.specs' => ['nullable', 'array'],
            ...$this->newComponentSpecRules(),
            'components.stock' => ['nullable', 'array'],
            // Harus benar-benar ada, masih In Stock, dan belum terpasang.
            'components.stock.*' => [
                'integer',
                Rule::exists('components', 'id')->whereNull('deleted_at'),
                function (string $attribute, mixed $value, Closure $fail) {
                    $component = Component::find($value);

                    if ($component !== null && ! $component->isInstallable()) {
                        $fail("Komponen {$component->component_code} tidak tersedia di gudang.");
                    }
                },
            ],
        ];
    }

    /**
     * Aturan `specs` untuk setiap kategori yang muncul di `components.new`.
     *
     * @return array<string, array<int, string>>
     */
    private function newComponentSpecRules(): array
    {
        $rules = [];

        foreach ((array) $this->input('components.new', []) as $index => $row) {
            $category = ComponentCategory::tryFrom((string) ($row['category'] ?? ''));

            if ($category === null) {
                continue;
            }

            foreach ($category->allSpecKeys() as $key) {
                $rules["components.new.{$index}.specs.{$key}"] = ['nullable', 'string', 'max:100'];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function componentAssemblyMessages(): array
    {
        return [
            'components.new.*.category.required' => 'Kategori komponen wajib dipilih.',
            'components.new.*.brand.required' => 'Merek komponen wajib diisi.',
            'components.new.*.serial_number.unique' => 'Nomor seri sudah dipakai komponen lain.',
            'components.stock.*.exists' => 'Komponen gudang yang dipilih tidak ditemukan.',
        ];
    }

    /**
     * Id komponen gudang yang dipilih.
     *
     * @return int[]
     */
    public function stockComponentIds(): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', (array) $this->input('components.stock', [])),
            fn (int $id) => $id > 0
        )));
    }

    /**
     * Baris komponen baru yang sudah dinormalisasi (siap di-create).
     *
     * @return array<int, array<string, mixed>>
     */
    public function newComponents(): array
    {
        $clean = [];

        foreach ((array) $this->input('components.new', []) as $row) {
            $category = ComponentCategory::tryFrom((string) ($row['category'] ?? ''));

            if ($category === null) {
                continue;
            }

            $specs = [];
            foreach ($category->allSpecKeys() as $key) {
                $value = $row['specs'][$key] ?? null;

                if (is_string($value) && trim($value) !== '') {
                    $specs[$key] = trim($value);
                }
            }

            $nullable = fn ($v) => (is_string($v) && trim($v) !== '') ? trim($v) : null;

            $clean[] = [
                'category' => $category,
                'brand' => trim((string) ($row['brand'] ?? '')),
                'model' => $nullable($row['model'] ?? null),
                'serial_number' => $nullable($row['serial_number'] ?? null),
                'specs' => $specs,
            ];
        }

        return $clean;
    }
}
