<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Password Awal Akun Seeder
    |--------------------------------------------------------------------------
    |
    | Password untuk akun yang dibuat UserSeeder. Diambil dari environment
    | agar tidak ada kredensial default yang ter-hardcode di repository.
    |
    | - seed_admin_password: akun admin@example.com. Bila kosong, di lokal
    |   seeder membuat password acak dan menampilkannya; di production seeder
    |   berhenti dengan error.
    | - seed_viewer_password: akun viewer@example.com (demo). Bila kosong di
    |   production, akun viewer TIDAK dibuat. Di lokal, viewer memakai
    |   password admin demi kemudahan.
    |
    */

    'seed_admin_password' => env('SEED_ADMIN_PASSWORD', 'password'),

    'seed_viewer_password' => env('SEED_VIEWER_PASSWORD'),

];
