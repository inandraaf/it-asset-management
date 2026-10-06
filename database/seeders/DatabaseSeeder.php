<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Seeder bersifat idempoten (aman dijalankan ulang).
     *
     * @see dokumentasi/12-acceptance-dan-roadmap.md M1
     */
    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            UserSeeder::class,
            EmployeeSeeder::class,
            AssetSeeder::class,
            ComponentSeeder::class,
        ]);
    }
}
