<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Jaring pengaman: tolak menjalankan test yang memakai RefreshDatabase bila
     * koneksi mengarah ke database non-test.
     *
     * `phpunit.xml` mengarahkan test ke database terpisah `inven_it_testing`,
     * tetapi override itu DIABAIKAN bila konfigurasi ter-cache
     * (`php artisan config:cache`). Tanpa guard ini, `RefreshDatabase` akan
     * menghapus seluruh isi database development.
     *
     * Dipanggil sebelum parent::setUpTraits() agar pengecekan terjadi
     * SEBELUM migrasi/refresh dijalankan.
     *
     * @see dokumentasi/13-operasional.md §4
     */
    protected function setUpTraits()
    {
        $uses = array_flip(class_uses_recursive(static::class));

        if (isset($uses[RefreshDatabase::class])) {
            $database = DB::connection()->getDatabaseName();

            if (! str_ends_with($database, '_testing')) {
                $this->fail(sprintf(
                    'Menolak menjalankan test pada database non-test [%s]. '
                    .'Jalankan `php artisan config:clear` lalu coba lagi.',
                    $database
                ));
            }
        }

        parent::setUpTraits();
    }
}
