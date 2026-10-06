<?php

namespace App\Http\Requests;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * @see dokumentasi/10-validasi.md §2
 */
class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_dept' => ['required', 'string', 'min:2', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['nama_dept' => trim((string) $this->nama_dept)]);
    }

    /**
     * Keunikan nama departemen harus case-insensitive ("RnD" == "rnd").
     *
     * Rule `unique` bawaan selalu membandingkan kolom secara persis
     * (case-sensitive) sehingga tidak bisa dipakai untuk kasus ini.
     * Pengecekan dilakukan manual dengan LOWER().
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('nama_dept')) {
                return;
            }

            $query = Department::query()
                ->whereRaw('LOWER(nama_dept) = ?', [mb_strtolower((string) $this->input('nama_dept'))]);

            $current = $this->route('department');

            if ($current) {
                $query->where('id', '!=', $current->id);
            }

            if ($query->exists()) {
                $validator->errors()->add('nama_dept', 'Nama departemen sudah digunakan.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'nama_dept.required' => 'Nama departemen wajib diisi.',
            'nama_dept.min' => 'Nama departemen minimal 2 karakter.',
            'nama_dept.max' => 'Nama departemen maksimal 100 karakter.',
        ];
    }
}
