<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mencegah browser menampilkan halaman dari bfcache (tombol Back).
 *
 * Masalah: setelah menyimpan aset/komponen, menekan tombol Back menampilkan
 * kembali FORM yang sudah terisi dari memori browser (bfcache). Bila admin
 * menekan Simpan lagi, data tersimpan DUPLIKAT.
 *
 * `Cache-Control: no-cache` saja TIDAK cukup — bfcache tetap aktif. Header
 * `no-store` memaksa browser membuang salinan halaman sehingga tombol Back
 * memuat ulang dari server dan form kembali kosong.
 *
 * Dipasang pada route yang menampilkan form input.
 *
 * @see dokumentasi/15-feedback-dan-tindak-lanjut.md W2
 */
class PreventBackCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
