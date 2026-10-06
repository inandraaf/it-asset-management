<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Database\Seeder;

/**
 * Data karyawan contoh untuk development.
 *
 * NIP dan nama realistis agar pencarian/filter mudah diuji. Idempoten:
 * memakai updateOrCreate pada NIP sebagai kunci, sehingga aman dijalankan ulang.
 *
 * @see dokumentasi/05-master-data.md §2
 */
class EmployeeSeeder extends Seeder
{
    /**
     * [nip, nama, nama_dept]
     */
    public const EMPLOYEES = [
        ['20210001', 'Budi Santoso', 'EDP'],
        ['20210002', 'Andi Wijaya', 'EDP'],
        ['20210003', 'Siti Aminah', 'EDP'],
        ['20220001', 'Dewi Lestari', 'HRGA'],
        ['20220002', 'Rina Marlina', 'HRGA'],
        ['20220003', 'Agus Setiawan', 'HRGA'],
        ['20230001', 'Fajar Nugroho', 'EP'],
        ['20230002', 'Putri Handayani', 'EP'],
        ['20230003', 'Hendra Gunawan', 'EP'],
        ['20240001', 'Rizky Pratama', 'RnD'],
        ['20240002', 'Maya Sari', 'RnD'],
        ['20240003', 'Bagus Permana', 'RnD'],
        ['20240004', 'Indra Kusuma', 'RnD'],
        ['20250001', 'Nadia Safitri', 'CC'],
        ['20250002', 'Yoga Prasetyo', 'CC'],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('EmployeeSeeder dilewati di production (data contoh).');

            return;
        }

        foreach (self::EMPLOYEES as [$nip, $nama, $namaDept]) {
            $department = Department::firstOrCreate(['nama_dept' => $namaDept]);

            Employee::updateOrCreate(
                ['nip' => $nip],
                ['nama' => $nama, 'department_id' => $department->id]
            );
        }
    }
}
