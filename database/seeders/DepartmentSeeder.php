<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

/**
 * 5 departemen awal sesuai PRD.
 *
 * @see dokumentasi/03-database.md §2
 */
class DepartmentSeeder extends Seeder
{
    public const DEPARTMENTS = ['EDP', 'HRGA', 'EP', 'RnD', 'CC'];

    public function run(): void
    {
        foreach (self::DEPARTMENTS as $nama) {
            Department::firstOrCreate(['nama_dept' => $nama]);
        }
    }
}
