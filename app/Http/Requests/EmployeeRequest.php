<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @see dokumentasi/10-validasi.md §3
 */
class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nip' => [
                'required',
                'string',
                'max:30',
                Rule::unique('employees', 'nip')->ignore($this->route('employee')?->id),
            ],
            'nama' => ['required', 'string', 'min:2', 'max:150'],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nip' => trim((string) $this->nip),
            'nama' => trim((string) $this->nama),
        ]);
    }

    public function messages(): array
    {
        return [
            'nip.required' => 'NIP wajib diisi.',
            'nip.unique' => 'NIP sudah terdaftar.',
            'nip.max' => 'NIP maksimal 30 karakter.',
            'nama.required' => 'Nama karyawan wajib diisi.',
            'nama.min' => 'Nama karyawan minimal 2 karakter.',
            'nama.max' => 'Nama karyawan maksimal 150 karakter.',
            'department_id.required' => 'Departemen wajib dipilih.',
            'department_id.exists' => 'Departemen tidak ditemukan.',
        ];
    }
}
