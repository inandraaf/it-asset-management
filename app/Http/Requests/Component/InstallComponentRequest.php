<?php

namespace App\Http\Requests\Component;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi pemasangan komponen ke host.
 *
 * @see dokumentasi/14-manajemen-komponen.md §7
 */
class InstallComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'component_id' => ['required', 'integer', Rule::exists('components', 'id')],
            'installed_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('notes') && blank($this->input('notes'))) {
            $this->merge(['notes' => null]);
        }
    }

    public function messages(): array
    {
        return [
            'component_id.required' => 'Komponen wajib dipilih.',
            'component_id.exists' => 'Komponen tidak ditemukan.',
            'installed_date.required' => 'Tanggal pasang wajib diisi.',
            'installed_date.before_or_equal' => 'Tanggal pasang tidak boleh di masa depan.',
        ];
    }
}
