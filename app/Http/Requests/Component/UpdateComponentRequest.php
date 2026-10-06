<?php

namespace App\Http\Requests\Component;

use App\Enums\ComponentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validasi perubahan komponen.
 *
 * `category` dan `component_code` **immutable** setelah dibuat:
 * - `component_code` memakai prefix kategori, jadi mengubah kategori akan
 *   membuat kode tidak konsisten.
 * - `status` `Installed` hanya boleh diubah lewat proses pasang/lepas.
 *
 * @see dokumentasi/14-manajemen-komponen.md
 */
class UpdateComponentRequest extends FormRequest
{
    use NormalizesComponentSpecs;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $component = $this->route('component');

        return [
            'brand' => ['required', 'string', 'min:1', 'max:100'],
            'model' => ['nullable', 'string', 'max:150'],
            'serial_number' => [
                'nullable', 'string', 'max:100',
                Rule::unique('components', 'serial_number')
                    ->ignore($component->id)
                    ->whereNull('deleted_at'),
            ],
            'specs' => ['nullable', 'array'],
            ...$this->specRules($component->category),
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::enum(ComponentStatus::class)],
            // 'category' sengaja tidak diikutkan -> tidak bisa diubah
        ];
    }

    protected function prepareForValidation(): void
    {
        // Kategori tidak berubah; pakai kategori komponen yang sedang diedit.
        $component = $this->route('component');

        $nullable = fn ($v) => ($v === null || trim((string) $v) === '') ? null : trim((string) $v);

        $this->merge([
            'brand' => trim((string) $this->input('brand')),
            'model' => $nullable($this->input('model')),
            'serial_number' => $nullable($this->input('serial_number')),
            'notes' => $nullable($this->input('notes')),
            'specs' => $this->normalizedSpecs($component?->category),
        ]);
    }

    /**
     * Status komponen yang sedang terpasang tidak boleh diubah manual menjadi
     * In Stock — harus lewat proses Lepas.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $component = $this->route('component');

            if ($component->status === ComponentStatus::Installed
                && $this->input('status') === ComponentStatus::InStock->value) {
                $validator->errors()->add(
                    'status',
                    'Komponen sedang terpasang. Gunakan fitur Lepas untuk mengembalikannya ke gudang.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'brand.required' => 'Merek wajib diisi.',
            'serial_number.unique' => 'Nomor seri sudah dipakai komponen lain.',
            'status.required' => 'Status wajib dipilih.',
        ];
    }
}
