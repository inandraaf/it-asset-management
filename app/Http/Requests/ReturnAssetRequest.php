<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @see dokumentasi/10-validasi.md §8
 */
class ReturnAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'returned_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'returned_date.required' => 'Tanggal return wajib diisi.',
            'returned_date.before_or_equal' => 'Tanggal return tidak boleh di masa depan.',
        ];
    }
}
