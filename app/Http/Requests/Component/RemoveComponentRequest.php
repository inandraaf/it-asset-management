<?php

namespace App\Http\Requests\Component;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi pelepasan komponen dari host.
 *
 * @see dokumentasi/14-manajemen-komponen.md §7
 */
class RemoveComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'removed_date' => ['required', 'date', 'before_or_equal:today'],
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
            'removed_date.required' => 'Tanggal lepas wajib diisi.',
            'removed_date.before_or_equal' => 'Tanggal lepas tidak boleh di masa depan.',
        ];
    }
}
