<?php

namespace App\Http\Requests\Component;

use App\Enums\ComponentCategory;
use App\Enums\ComponentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi input komponen baru.
 *
 * Kode komponen TIDAK diterima dari form: selalu dibuat sistem lewat
 * CodeGenerator agar tidak ada tabrakan atau human error.
 *
 * @see dokumentasi/14-manajemen-komponen.md
 */
class StoreComponentRequest extends FormRequest
{
    use NormalizesComponentSpecs;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(ComponentCategory::class)],
            'brand' => ['required', 'string', 'min:1', 'max:100'],
            'model' => ['nullable', 'string', 'max:150'],
            'serial_number' => [
                'nullable', 'string', 'max:100',
                Rule::unique('components', 'serial_number')->whereNull('deleted_at'),
            ],
            'specs' => ['nullable', 'array'],
            ...$this->specRules($this->categoryFromInput()),
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['sometimes', Rule::enum(ComponentStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'category.required' => 'Kategori komponen wajib dipilih.',
            'brand.required' => 'Merek wajib diisi.',
            'serial_number.unique' => 'Nomor seri sudah dipakai komponen lain.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepareComponentForValidation();
    }
}
