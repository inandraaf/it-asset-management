<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @see dokumentasi/10-validasi.md §8b
 */
class TransferAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'transfer_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'Karyawan tujuan wajib dipilih.',
            'employee_id.exists' => 'Karyawan tidak ditemukan.',
            'transfer_date.required' => 'Tanggal transfer wajib diisi.',
            'transfer_date.before_or_equal' => 'Tanggal transfer tidak boleh di masa depan.',
        ];
    }
}
