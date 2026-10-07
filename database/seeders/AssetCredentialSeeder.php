<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetCredential;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Kredensial contoh untuk development (S5).
 *
 * Menunjukkan pola nyata: satu PC dengan beberapa akun (Admin + User Biasa)
 * dan satu kredensial VNC.
 *
 * Idempoten: kunci alami (asset_id, label).
 */
class AssetCredentialSeeder extends Seeder
{
    /** [hostname, label, username, password, notes] */
    private const CREDENTIALS = [
        ['pc-rnd-01', 'Admin Windows', 'admin.local', 'AdminRnd01!', 'Akun administrator lokal'],
        ['pc-rnd-01', 'User Windows', 'user.rnd01', 'UserRnd01!', 'Akun harian karyawan'],
        ['pc-rnd-01', 'VNC', null, 'VncRnd01$', 'VNC port 5900'],
        ['pc-rnd-02', 'Admin Windows', 'admin.local', 'AdminRnd02!', null],
        ['pc-rnd-02', 'User Windows', 'user.rnd02', 'UserRnd02!', null],
        ['pc-edp-01', 'Admin Windows', 'admin.local', 'AdminEdp01!', null],
        ['pc-edp-01', 'VNC', null, 'VncEdp01$', 'VNC port 5900'],
        ['pc-cc-01', 'Admin Windows', 'admin.local', 'AdminCc01!', null],
        ['lt-rnd-01', 'Windows', 'rnd.user', 'LtRnd01!', null],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('AssetCredentialSeeder dilewati di production (data contoh).');

            return;
        }

        $adminId = User::query()->value('id');

        foreach (self::CREDENTIALS as [$hostname, $label, $username, $password, $notes]) {
            $asset = Asset::where('hostname', strtolower($hostname))->first();

            if (! $asset) {
                continue;
            }

            AssetCredential::updateOrCreate(
                ['asset_id' => $asset->id, 'label' => $label],
                [
                    'username' => $username,
                    'password' => $password,
                    'notes' => $notes,
                    'created_by' => $adminId,
                ]
            );
        }
    }
}
