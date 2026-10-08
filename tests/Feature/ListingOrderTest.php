<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Component;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Urutan daftar aset & komponen (W3).
 *
 * Konsep: aset yang BARU SAJA dibuat tampil di baris pertama **sekali saja**.
 * Kunjungan berikutnya (refresh / pindah halaman lalu kembali) otomatis
 * kembali ke urutan **kode aset**.
 *
 * @see dokumentasi/15-feedback-dan-tindak-lanjut.md W3
 */
class ListingOrderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    // -------------------------------------------------- Urutan dasar

    public function test_asset_list_is_ordered_by_asset_code(): void
    {
        Asset::factory()->pc()->create(['asset_code' => 'PC-2026-0003']);
        Asset::factory()->pc()->create(['asset_code' => 'PC-2026-0001']);
        Asset::factory()->pc()->create(['asset_code' => 'PC-2026-0002']);

        $this->actingAs($this->admin())
            ->get(route('assets.index'))
            ->assertOk()
            ->assertSeeInOrder(['PC-2026-0001', 'PC-2026-0002', 'PC-2026-0003']);
    }

    /**
     * Inilah bug aslinya: banyak aset dengan `created_at` identik membuat
     * urutan tidak stabil. Urutan kode + `id` sebagai tie-breaker
     * menghilangkan ketidakstabilan itu.
     */
    public function test_order_is_stable_when_timestamps_are_identical(): void
    {
        $sameMoment = now();

        foreach (['PC-2026-0005', 'PC-2026-0002', 'PC-2026-0009', 'PC-2026-0001'] as $code) {
            Asset::factory()->pc()->create([
                'asset_code' => $code,
                'created_at' => $sameMoment,
                'updated_at' => $sameMoment,
            ]);
        }

        $expected = ['PC-2026-0001', 'PC-2026-0002', 'PC-2026-0005', 'PC-2026-0009'];

        // Dijalankan 3× untuk memastikan hasilnya konsisten.
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($this->admin())
                ->get(route('assets.index'))
                ->assertOk()
                ->assertSeeInOrder($expected);
        }
    }

    public function test_component_list_is_ordered_by_component_code(): void
    {
        Component::factory()->create(['component_code' => 'RAM-2026-0002']);
        Component::factory()->create(['component_code' => 'RAM-2026-0001']);

        $this->actingAs($this->admin())
            ->get(route('components.index'))
            ->assertOk()
            ->assertSeeInOrder(['RAM-2026-0001', 'RAM-2026-0002']);
    }

    // ------------------------------------------- Highlight sekali pakai

    public function test_new_asset_appears_first_on_first_visit(): void
    {
        $old = Asset::factory()->pc()->create(['asset_code' => 'PC-2026-0001']);
        $admin = $this->admin();

        // Buat aset baru lewat form (kode jadi PC-2026-0002, urutan kode di bawah PC-2026-0001).
        $this->actingAs($admin)->post(route('assets.store'), [
            'type' => 'PC',
            'brand' => 'Dell',
            'mac_address' => 'AA:BB:CC:DD:EE:99',
            'specs' => [],
        ]);

        $new = Asset::where('asset_code', '!=', 'PC-2026-0001')->sole();

        // Kunjungan PERTAMA: aset baru di atas, walau kodenya lebih besar.
        // Urutan kode akan menaruh PC-2026-0001 lebih dulu.
        $this->actingAs($admin)->get(route('assets.index'))
            ->assertOk()
            ->assertSeeInOrder([$new->asset_code, $old->asset_code], false);

        // Kunjungan KEDUA: kembali urut kode.
        $this->actingAs($admin)->get(route('assets.index'))
            ->assertOk()
            ->assertSeeInOrder([$old->asset_code, $new->asset_code], false);
    }

    public function test_highlight_is_consumed_after_one_visit(): void
    {
        $admin = $this->admin();
        Asset::factory()->pc()->create(['asset_code' => 'PC-2026-0001']);

        $this->actingAs($admin)->post(route('assets.store'), [
            'type' => 'PC',
            'brand' => 'Dell',
            'mac_address' => 'AA:BB:CC:DD:EE:98',
            'specs' => [],
        ]);

        // Kunjungan pertama memakai highlight.
        $this->actingAs($admin)->get(route('assets.index'))
            ->assertOk()
            ->assertSessionMissing('highlight_asset_id');

        // Tidak ada highlight tersisa.
        $this->assertNull(session('highlight_asset_id'));
    }

    public function test_new_component_appears_first_on_first_visit(): void
    {
        $old = Component::factory()->create(['component_code' => 'RAM-2026-0001']);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('components.store'), [
            'category' => 'ram',
            'brand' => 'Kingston',
            'specs' => ['capacity' => '8GB', 'type' => 'DDR4'],
        ]);

        $new = Component::where('component_code', '!=', 'RAM-2026-0001')->sole();

        $this->actingAs($admin)->get(route('components.index'))
            ->assertOk()
            ->assertSeeInOrder([$new->component_code, $old->component_code], false);

        // Kunjungan kedua: urut kode.
        $this->actingAs($admin)->get(route('components.index'))
            ->assertOk()
            ->assertSeeInOrder([$old->component_code, $new->component_code], false);
    }

    /**
     * Baris aset baru diberi sorotan visual.
     */
    public function test_new_asset_row_is_highlighted(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('assets.store'), [
            'type' => 'PC',
            'brand' => 'Dell',
            'mac_address' => 'AA:BB:CC:DD:EE:97',
            'specs' => [],
        ]);

        $this->actingAs($admin)
            ->get(route('assets.index'))
            ->assertOk()
            ->assertSee('data-highlighted="true"', false);
    }

    /**
     * Kunjungan biasa (tanpa baru membuat) tidak menyorot apa pun.
     */
    public function test_no_highlight_on_normal_visit(): void
    {
        Asset::factory()->pc()->create();

        $this->actingAs($this->admin())
            ->get(route('assets.index'))
            ->assertOk()
            ->assertDontSee('data-highlighted="true"', false);
    }

    // ------------------------------- W4: urut jenis lalu kode

    public function test_asset_types_have_canonical_sort_order(): void
    {
        $this->assertSame(1, \App\Enums\AssetType::PC->sortOrder());
        $this->assertSame(2, \App\Enums\AssetType::Laptop->sortOrder());
        $this->assertSame(3, \App\Enums\AssetType::Cctv->sortOrder());
        $this->assertSame(4, \App\Enums\AssetType::Printer->sortOrder());
    }

    public function test_sql_sort_case_puts_unknown_types_last(): void
    {
        $sql = \App\Enums\AssetType::sqlSortCase('assets.type');

        $this->assertStringContainsString("WHEN assets.type = 'PC' THEN 1", $sql);
        $this->assertStringContainsString("WHEN assets.type = 'Printer' THEN 4", $sql);
        $this->assertStringContainsString('ELSE 99 END', $sql);
    }

    /**
     * Urutan daftar: PC → Laptop → CCTV → Printer, lalu kode aset.
     */
    public function test_assets_are_ordered_by_type_then_code(): void
    {
        // Dibuat terbalik dari urutan yang diharapkan.
        Asset::factory()->printer()->create(['asset_code' => 'PRN-2026-0001']);
        Asset::factory()->cctv()->create(['asset_code' => 'CCTV-2026-0001']);
        Asset::factory()->laptop()->create(['asset_code' => 'LT-2026-0002']);
        Asset::factory()->laptop()->create(['asset_code' => 'LT-2026-0001']);
        Asset::factory()->pc()->create(['asset_code' => 'PC-2026-0002']);
        Asset::factory()->pc()->create(['asset_code' => 'PC-2026-0001']);

        $this->actingAs($this->admin())
            ->get(route('assets.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'PC-2026-0001', 'PC-2026-0002',
                'LT-2026-0001', 'LT-2026-0002',
                'CCTV-2026-0001',
                'PRN-2026-0001',
            ], false);
    }

    /**
     * Highlight tetap berlaku walau urutan jenis aktif: aset baru naik ke atas
     * apa pun jenisnya.
     */
    public function test_highlight_still_wins_over_type_ordering(): void
    {
        $admin = $this->admin();

        // Sudah ada PC (jenis pertama).
        Asset::factory()->pc()->create(['asset_code' => 'PC-2026-0001']);

        // Buat Printer — secara jenis ia paling akhir.
        $this->actingAs($admin)->post(route('assets.store'), [
            'type' => 'Printer',
            'brand' => 'Epson',
            'department_id' => \App\Models\Department::create(['nama_dept' => 'HRGA'])->id,
            'specs' => [],
        ]);

        $printer = Asset::where('type', 'Printer')->sole();

        // Kunjungan pertama: Printer di ATAS meski jenisnya terakhir.
        $this->actingAs($admin)->get(route('assets.index'))
            ->assertOk()
            ->assertSeeInOrder([$printer->asset_code, 'PC-2026-0001'], false);

        // Kunjungan kedua: kembali urut jenis (PC dulu).
        $this->actingAs($admin)->get(route('assets.index'))
            ->assertOk()
            ->assertSeeInOrder(['PC-2026-0001', $printer->asset_code], false);
    }

    // ------------------------- W4b: seeder idempoten walau MAC null

    /**
     * Seeder dulu memakai `mac_address` sebagai kunci idempoten. Printer/CCTV
     * boleh tidak punya MAC, sehingga mencari `mac_address = null` akan keliru
     * menemukan aset lain dan membuat Printer tidak pernah dibuat.
     */
    public function test_seeder_creates_all_asset_types(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        foreach (['PC', 'Laptop', 'CCTV', 'Printer'] as $type) {
            $this->assertGreaterThan(
                0,
                Asset::where('type', $type)->count(),
                "Seeder tidak membuat aset berjenis {$type}."
            );
        }
    }

    public function test_seeder_is_idempotent_with_null_mac_addresses(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $first = Asset::count();

        // Dijalankan ulang — jumlah tidak boleh bertambah.
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->assertSame($first, Asset::count(), 'Seeder menggandakan data saat dijalankan ulang.');
    }

    public function test_seeder_gives_printers_a_department(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        // Printer melekat departemen; CCTV tidak.
        $this->assertGreaterThan(0, Asset::where('type', 'Printer')->whereNotNull('department_id')->count());
        $this->assertSame(0, Asset::where('type', 'CCTV')->whereNotNull('department_id')->count());
    }

    public function test_cctv_may_have_null_mac(): void
    {
        Asset::factory()->cctv()->create();

        $this->assertNull(Asset::where('type', 'CCTV')->sole()->mac_address);
    }
}
