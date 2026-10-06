<?php

namespace App\Http\Requests\Component;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi pemindahan komponen ke host lain.
 *
 * @see dokumentasi/14-manajemen-komponen.md §13
 */
class MoveComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'integer', Rule::exists('assets', 'id')],
            'move_date' => ['required', 'date', 'before_or_equal:today'],
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
            'asset_id.required' => 'Host tujuan wajib dipilih.',
            'asset_id.exists' => 'Host tidak ditemukan.',
            'move_date.required' => 'Tanggal pindah wajib diisi.',
            'move_date.before_or_equal' => 'Tanggal pindah tidak boleh di masa depan.',
        ];
    }
}
