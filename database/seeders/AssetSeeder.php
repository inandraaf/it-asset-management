<?php

namespace Database\Seeders;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Services\CodeGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Data aset contoh untuk development.
 *
 * Kode aset tetap dibuat lewat CodeGenerator (bukan ditulis manual) agar
 * seeder menghasilkan data yang sama seperti alur aplikasi sebenarnya.
 *
 * Idempoten: kunci alami adalah `hostname` (selalu ada dan unik, termasuk untuk
 * CCTV/Printer yang boleh tanpa MAC). Menjalankan ulang tidak menggandakan data
 * dan tidak mengubah kode aset yang sudah ada.
 *
 * @see dokumentasi/06-manajemen-aset.md
 * @see dokumentasi/15-feedback-dan-tindak-lanjut.md W4b
 */
class AssetSeeder extends Seeder
{
    /**
     * Definisi aset.
     *
     * Urutan: type, brand, hostname, mac, ip, specs, status, holderNip
     * holderNip null = belum dipegang siapa pun.
     * `specs` host hanya menyimpan OS (Fase 2); part fisik ada di ComponentSeeder.
     */
    private const ASSETS = [
        // --- PC ---
        [
            'PC', 'Dell OptiPlex 7090', 'PC-RND-01', 'AA:11:00:00:00:01', '192.168.10.11',
            ['cpu' => 'Intel Core i7-11700', 'ram' => '32GB (2x16GB) DDR4', 'storage' => '1TB NVMe SSD',
                'gpu' => 'NVIDIA GTX 1650 4GB', 'motherboard' => 'Dell 0K1TT7', 'psu' => '360W OEM',
                'casing' => 'Dell SFF', 'os' => 'Windows 11 Pro 64-bit',
                'monitor' => 'Dell P2419H 24"', 'keyboard' => 'Dell KB216', 'mouse' => 'Dell MS116'],
            AssetStatus::Assigned, '20240001',
        ],
        [
            'PC', 'Dell OptiPlex 7090', 'PC-RND-02', 'AA:11:00:00:00:02', '192.168.10.12',
            ['cpu' => 'Intel Core i7-11700', 'ram' => '32GB (2x16GB) DDR4', 'storage' => '1TB NVMe SSD',
                'gpu' => 'NVIDIA RTX 3060 12GB', 'motherboard' => 'Dell 0K1TT7', 'psu' => '500W 80+ Bronze',
                'casing' => 'Dell SFF', 'os' => 'Windows 11 Pro 64-bit',
                'monitor' => 'LG 27UL500 27"', 'keyboard' => 'Logitech K120', 'mouse' => 'Logitech B100'],
            AssetStatus::Assigned, '20240002',
        ],
        [
            'PC', 'HP ProDesk 400 G7', 'PC-EDP-01', 'AA:11:00:00:00:03', '192.168.10.21',
            ['cpu' => 'Intel Core i5-10500', 'ram' => '16GB (2x8GB) DDR4', 'storage' => '512GB NVMe SSD',
                'storage_2' => '1TB HDD', 'motherboard' => 'HP 8768', 'psu' => '180W OEM',
                'casing' => 'HP SFF', 'os' => 'Windows 10 Pro 64-bit',
                'monitor' => 'HP P22h G4 21.5"', 'keyboard' => 'HP KB-0316', 'mouse' => 'HP X500'],
            AssetStatus::Assigned, '20210001',
        ],
        [
            'PC', 'HP ProDesk 400 G7', 'PC-EDP-02', 'AA:11:00:00:00:04', '192.168.10.22',
            ['cpu' => 'Intel Core i5-10500', 'ram' => '16GB (2x8GB) DDR4', 'storage' => '512GB NVMe SSD',
                'motherboard' => 'HP 8768', 'psu' => '180W OEM', 'casing' => 'HP SFF',
                'os' => 'Windows 10 Pro 64-bit', 'monitor' => 'HP P22h G4 21.5"',
                'keyboard' => 'HP KB-0316', 'mouse' => 'HP X500'],
            AssetStatus::Assigned, '20210002',
        ],
        [
            'PC', 'Lenovo ThinkCentre M720', 'PC-HRGA-01', 'AA:11:00:00:00:05', '192.168.10.31',
            ['cpu' => 'Intel Core i5-9400', 'ram' => '8GB DDR4', 'storage' => '256GB SSD',
                'motherboard' => 'Lenovo 312D', 'psu' => '180W OEM', 'casing' => 'Lenovo Tiny',
                'os' => 'Windows 10 Pro 64-bit', 'monitor' => 'Lenovo D22e-20 21.5"',
                'keyboard' => 'Lenovo KU-1601', 'mouse' => 'Lenovo M110'],
            AssetStatus::Assigned, '20220001',
        ],
        [
            'PC', 'Acer Veriton X2660G', 'PC-EP-01', 'AA:11:00:00:00:06', '192.168.10.41',
            ['cpu' => 'Intel Core i3-9100', 'ram' => '8GB DDR4', 'storage' => '256GB SSD',
                'motherboard' => 'Acer X2660G', 'psu' => '250W OEM', 'casing' => 'Acer SFF',
                'os' => 'Windows 10 Pro 64-bit', 'monitor' => 'Acer V206HQL 19.5"',
                'keyboard' => 'Acer KB', 'mouse' => 'Acer MS'],
            AssetStatus::Assigned, '20230001',
        ],
        [
            'PC', 'Lenovo ThinkCentre M720', 'PC-CC-01', 'AA:11:00:00:00:07', '192.168.10.51',
            ['cpu' => 'Intel Core i5-9400', 'ram' => '16GB (2x8GB) DDR4', 'storage' => '512GB SSD',
                'motherboard' => 'Lenovo 312D', 'psu' => '180W OEM', 'casing' => 'Lenovo Tiny',
                'os' => 'Windows 11 Pro 64-bit', 'monitor' => 'Lenovo D22e-20 21.5"',
                'keyboard' => 'Lenovo KU-1601', 'mouse' => 'Lenovo M110'],
            AssetStatus::Assigned, '20250001',
        ],
        [
            'PC', 'HP ProDesk 400 G7', 'PC-EDP-03', 'AA:11:00:00:00:08', null,
            ['cpu' => 'Intel Core i5-10500', 'ram' => '8GB DDR4', 'storage' => '512GB NVMe SSD',
                'motherboard' => 'HP 8768', 'psu' => '180W OEM', 'casing' => 'HP SFF',
                'os' => 'Windows 11 Pro 64-bit'],
            AssetStatus::Available, null,
        ],
        [
            'PC', 'Dell OptiPlex 3070', 'PC-RND-03', 'AA:11:00:00:00:09', null,
            ['cpu' => 'Intel Core i3-9100', 'ram' => '8GB DDR4', 'storage' => '256GB SSD',
                'motherboard' => 'Dell 0F6P8V', 'psu' => '200W OEM', 'casing' => 'Dell SFF',
                'os' => 'Windows 10 Pro 64-bit'],
            AssetStatus::InRepair, null,
        ],
        [
            'PC', 'Acer Veriton X2660G', 'PC-HRGA-02', 'AA:11:00:00:00:10', null,
            ['cpu' => 'Intel Core i3-8100', 'ram' => '4GB DDR4', 'storage' => '500GB HDD',
                'motherboard' => 'Acer X2660G', 'psu' => '250W OEM', 'casing' => 'Acer SFF',
                'os' => 'Windows 10 Pro 64-bit'],
            AssetStatus::Retired, null,
        ],

        // --- Laptop ---
        [
            'Laptop', 'Lenovo ThinkPad T14 Gen 2', 'LT-RND-01', 'BB:22:00:00:00:01', '192.168.10.111',
            ['cpu' => 'Intel Core i7-1165G7', 'ram' => '16GB DDR4', 'storage' => '1TB NVMe SSD',
                'gpu' => 'Intel Iris Xe', 'os' => 'Windows 11 Pro 64-bit'],
            AssetStatus::Assigned, '20240003',
        ],
        [
            'Laptop', 'HP EliteBook 840 G8', 'LT-EP-01', 'BB:22:00:00:00:02', '192.168.10.121',
            ['cpu' => 'Intel Core i5-1135G7', 'ram' => '16GB DDR4', 'storage' => '512GB NVMe SSD',
                'gpu' => 'Intel Iris Xe', 'os' => 'Windows 11 Pro 64-bit'],
            AssetStatus::Assigned, '20230002',
        ],
        [
            'Laptop', 'Asus ZenBook 14 UX425', 'LT-CC-01', 'BB:22:00:00:00:03', '192.168.10.131',
            ['cpu' => 'Intel Core i5-1135G7', 'ram' => '16GB LPDDR4X', 'storage' => '512GB NVMe SSD',
                'gpu' => 'Intel Iris Xe', 'os' => 'Windows 11 Home 64-bit'],
            AssetStatus::Assigned, '20250002',
        ],
        [
            'Laptop', 'Lenovo ThinkPad E14', 'LT-EDP-01', 'BB:22:00:00:00:04', null,
            ['cpu' => 'Intel Core i5-10210U', 'ram' => '8GB DDR4', 'storage' => '512GB NVMe SSD',
                'gpu' => 'Intel UHD Graphics', 'os' => 'Windows 10 Pro 64-bit'],
            AssetStatus::Available, null,
        ],
        [
            'Laptop', 'Asus ZenBook 14 UX425', 'LT-RND-02', 'BB:22:00:00:00:05', null,
            ['cpu' => 'Intel Core i7-1165G7', 'ram' => '16GB LPDDR4X', 'storage' => '1TB NVMe SSD',
                'gpu' => 'Intel Iris Xe', 'os' => 'Windows 11 Pro 64-bit'],
            AssetStatus::Available, null,
        ],
        [
            'Laptop', 'HP EliteBook 840 G7', 'LT-EP-02', 'BB:22:00:00:00:06', null,
            ['cpu' => 'Intel Core i5-10310U', 'ram' => '8GB DDR4', 'storage' => '256GB NVMe SSD',
                'gpu' => 'Intel UHD Graphics', 'os' => 'Windows 10 Pro 64-bit'],
            AssetStatus::InRepair, null,
        ],

        // --- CCTV (tanpa pemilik, tanpa MAC wajib) ---
        [
            'CCTV', 'Hikvision DS-2CD2143G2', 'CCTV-EDP-01', 'CC:33:00:00:00:01', '192.168.20.11',
            ['os' => 'Firmware v5.7'],
            AssetStatus::Available, null,
        ],
        [
            'CCTV', 'Hikvision DS-2CD2143G2', 'CCTV-EDP-02', 'CC:33:00:00:00:02', '192.168.20.12',
            ['os' => 'Firmware v5.7'],
            AssetStatus::Available, null,
        ],
        [
            'CCTV', 'Dahua IPC-HFW1230S', 'CCTV-CC-01', null, '192.168.20.21',
            ['os' => 'Firmware v4.0'],
            AssetStatus::InRepair, null,
        ],

        // --- Printer (melekat departemen) ---
        [
            'Printer', 'Epson L3210', 'PRN-HRGA-01', null, '192.168.30.11',
            ['os' => 'Firmware 1.2'],
            AssetStatus::Available, null,
        ],
        [
            'Printer', 'HP LaserJet M404dn', 'PRN-EDP-01', null, '192.168.30.12',
            ['os' => 'Firmware 2.1'],
            AssetStatus::Available, null,
        ],
    ];

    /**
     * Aset departemen: hostname => nama departemen pemilik.
     * Printer melekat departemen; CCTV tanpa pemilik (tanggung jawab Admin IT).
     */
    private const DEPARTMENT_OWNED = [
        'prn-hrga-01' => 'HRGA',
        'prn-edp-01' => 'EDP',
    ];

    /**
     * Lokasi fisik CCTV (X2): hostname => lokasi.
     */
    private const LOCATIONS = [
        'cctv-edp-01' => 'Lobby Utama',
        'cctv-edp-02' => 'Gudang EDP',
        'cctv-cc-01' => 'Parkiran',
    ];

    /**
     * Riwayat transfer contoh: [hostname, nip pemegang lama, tanggal assign lama,
     * tanggal return, nip pemegang baru, tanggal assign baru].
     */
    private const TRANSFERS = [
        ['PC-EDP-01', '20210003', '-120 days', '-60 days', '20210001', '-60 days'],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('AssetSeeder dilewati di production (data contoh).');

            return;
        }

        $generator = app(CodeGenerator::class);
        $adminId = User::query()->value('id');

        // 1. Buat seluruh aset lebih dulu (tanpa penugasan).
        $byHostname = [];

        foreach (self::ASSETS as [$type, $brand, $hostname, $mac, $ip, $specs, $status, $holderNip]) {
            // Idempoten berdasarkan HOSTNAME, bukan MAC: Printer/CCTV boleh
            // tidak punya MAC, sehingga mencari `mac_address = null` akan
            // keliru menemukan aset lain.
            $asset = Asset::where('hostname', strtolower($hostname))->first()
                ?? DB::transaction(fn () => Asset::create([
                    'asset_code' => $generator->next(AssetType::from($type)),
                    'type' => $type,
                    'brand' => $brand,
                    // Hostname disimpan lowercase, konsisten dengan normalisasi
                    // di StoreAssetRequest.
                    'hostname' => strtolower($hostname),
                    'mac_address' => $mac,
                    'ip_address' => $ip,
                    'specs' => ['os' => $specs['os'] ?? 'Windows 11 Pro 64-bit'],
                    'status' => $status,
                    'created_by' => $adminId,
                ]));

            // Printer melekat pada departemen; CCTV tanpa pemilik.
            if ($deptName = self::DEPARTMENT_OWNED[strtolower($hostname)] ?? null) {
                $asset->update([
                    'department_id' => Department::where('nama_dept', $deptName)->value('id'),
                ]);
            }

            // Lokasi fisik CCTV (X2).
            if ($location = self::LOCATIONS[strtolower($hostname)] ?? null) {
                $asset->update(['location' => $location]);
            }

            $byHostname[$hostname] = [$asset, $holderNip];
        }

        // 2. Riwayat transfer lebih dulu, agar tidak tumpang tindih dengan
        //    penugasan tunggal di langkah 3.
        $this->seedTransferHistory($adminId);

        // 3. Penugasan tunggal untuk aset yang belum punya riwayat sama sekali.
        foreach ($byHostname as [$asset, $holderNip]) {
            if ($holderNip !== null) {
                $this->assign($asset, $holderNip, $adminId);
            }
        }
    }

    /**
     * Buat satu assignment aktif bila belum ada.
     */
    private function assign(Asset $asset, string $holderNip, ?int $adminId): void
    {
        $employee = Employee::where('nip', $holderNip)->first();

        if (! $employee) {
            $this->command?->warn("Karyawan NIP {$holderNip} tidak ditemukan; aset {$asset->asset_code} dilewati.");

            return;
        }

        $alreadyHeld = AssetAssignment::where('asset_id', $asset->id)
            ->whereNull('returned_date')
            ->exists();

        if ($alreadyHeld) {
            return;
        }

        AssetAssignment::create([
            'asset_id' => $asset->id,
            'employee_id' => $employee->id,
            'assigned_date' => now()->subDays(90)->format('Y-m-d'),
            'returned_date' => null,
            'notes' => 'Data contoh seeder.',
            'assigned_by' => $adminId,
        ]);

        $asset->update(['status' => AssetStatus::Assigned]);
    }

    /**
     * Buat riwayat transfer (dua baris: lama ditutup, baru aktif).
     */
    private function seedTransferHistory(?int $adminId): void
    {
        foreach (self::TRANSFERS as [$hostname, $fromNip, $fromAssigned, $returned, $toNip, $toAssigned]) {
            $asset = Asset::where('hostname', strtolower($hostname))->first();

            if (! $asset) {
                continue;
            }

            // Lewati bila riwayat untuk aset ini sudah pernah dibuat.
            if (AssetAssignment::where('asset_id', $asset->id)->exists()) {
                continue;
            }

            $from = Employee::where('nip', $fromNip)->first();
            $to = Employee::where('nip', $toNip)->first();

            if (! $from || ! $to) {
                continue;
            }

            DB::transaction(function () use ($asset, $from, $to, $fromAssigned, $returned, $toAssigned, $adminId) {
                AssetAssignment::create([
                    'asset_id' => $asset->id,
                    'employee_id' => $from->id,
                    'assigned_date' => Carbon::parse($fromAssigned)->format('Y-m-d'),
                    'returned_date' => Carbon::parse($returned)->format('Y-m-d'),
                    'notes' => 'Data contoh seeder (transfer).',
                    'assigned_by' => $adminId,
                ]);

                AssetAssignment::create([
                    'asset_id' => $asset->id,
                    'employee_id' => $to->id,
                    'assigned_date' => Carbon::parse($toAssigned)->format('Y-m-d'),
                    'returned_date' => null,
                    'notes' => 'Data contoh seeder (transfer).',
                    'assigned_by' => $adminId,
                ]);

                $asset->update(['status' => AssetStatus::Assigned]);
            });
        }
    }
}
