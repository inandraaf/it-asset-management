<?php

namespace Tests\Feature;

use App\Enums\ComponentCategory;
use App\Enums\ComponentStatus;
use App\Models\Asset;
use App\Models\Component;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rakit komponen saat aset BARU dibuat (X3).
 *
 * Satu submit menyimpan: aset + komponen pengadaan baru + pemilihan komponen
 * gudang + pemasangan, semuanya dalam satu transaksi.
 *
 * @see dokumentasi/15-feedback-dan-tindak-lanjut.md X3
 */
class AssetAssemblyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function assetPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'PC',
            'brand' => 'Dell',
            'mac_address' => 'AA:BB:CC:DD:EE:31',
            'specs' => ['os' => 'Windows 11'],
        ], $overrides);
    }

    public function test_asset_can_be_created_with_new_components(): void
    {
        $this->actingAs($this->admin())->post(route('assets.store'), $this->assetPayload([
            'components' => [
                'new' => [
                    ['category' => 'ram', 'brand' => 'Kingston', 'specs' => ['capacity' => '16GB', 'type' => 'DDR4']],
                    ['category' => 'storage', 'brand' => 'Samsung', 'specs' => ['capacity' => '512GB', 'type' => 'SSD']],
                ],
            ],
        ]))->assertRedirect();

        $asset = Asset::where('type', 'PC')->sole();

        // Dua komponen dibuat & langsung terpasang.
        $this->assertSame(2, Component::count());
        $this->assertSame(2, $asset->activeComponentInstallations()->count());

        $ram = Component::where('category', 'ram')->sole();
        $this->assertSame('Kingston', $ram->brand);
        $this->assertSame(ComponentStatus::Installed, $ram->status);
        $this->assertSame($asset->id, $ram->currentAsset()->id);
    }

    public function test_asset_can_be_created_with_stock_components(): void
    {
        $stock = Component::factory()->ofCategory(ComponentCategory::Ram)->create([
            'brand' => 'Corsair',
            'specs' => ['capacity' => '8GB', 'type' => 'DDR4'],
        ]);

        $this->actingAs($this->admin())->post(route('assets.store'), $this->assetPayload([
            'components' => ['stock' => [$stock->id]],
        ]))->assertRedirect();

        $asset = Asset::where('type', 'PC')->sole();

        $this->assertSame(1, $asset->activeComponentInstallations()->count());
        $this->assertSame(ComponentStatus::Installed, $stock->fresh()->status);
        // Komponen gudang tidak digandakan.
        $this->assertSame(1, Component::count());
    }

    public function test_new_and_stock_components_can_be_mixed(): void
    {
        $stock = Component::factory()->ofCategory(ComponentCategory::Cpu)->create(['brand' => 'Intel']);

        $this->actingAs($this->admin())->post(route('assets.store'), $this->assetPayload([
            'components' => [
                'new' => [
                    ['category' => 'ram', 'brand' => 'Kingston', 'specs' => ['capacity' => '16GB', 'type' => 'DDR4']],
                ],
                'stock' => [$stock->id],
            ],
        ]))->assertRedirect();

        $asset = Asset::where('type', 'PC')->sole();

        $this->assertSame(2, $asset->activeComponentInstallations()->count());
        $this->assertSame(2, Component::count());
    }

    public function test_asset_without_components_still_works(): void
    {
        $this->actingAs($this->admin())->post(route('assets.store'), $this->assetPayload())
            ->assertRedirect();

        $this->assertSame(0, Component::count());
        $this->assertSame(0, Asset::where('type', 'PC')->sole()->activeComponentInstallations()->count());
    }

    public function test_component_code_is_generated_per_category(): void
    {
        $this->actingAs($this->admin())->post(route('assets.store'), $this->assetPayload([
            'components' => [
                'new' => [
                    ['category' => 'ram', 'brand' => 'Kingston', 'specs' => []],
                    ['category' => 'storage', 'brand' => 'Samsung', 'specs' => []],
                ],
            ],
        ]))->assertRedirect();

        $this->assertSame(1, Component::where('category', 'ram')->count());
        $this->assertStringStartsWith('RAM-', Component::where('category', 'ram')->sole()->component_code);
        $this->assertStringStartsWith('DSK-', Component::where('category', 'storage')->sole()->component_code);
    }

    public function test_new_component_requires_brand(): void
    {
        $this->actingAs($this->admin())->post(route('assets.store'), $this->assetPayload([
            'components' => [
                'new' => [['category' => 'ram', 'brand' => '', 'specs' => []]],
            ],
        ]))->assertSessionHasErrors('components.new.0.brand');

        $this->assertSame(0, Asset::count());
        $this->assertSame(0, Component::count());
    }

    /**
     * Komponen yang sudah terpasang di host lain tidak boleh dipakai.
     */
    public function test_stock_component_must_be_installable(): void
    {
        // Host lain yang sedang memegang komponen tersebut.
        $otherAsset = Asset::factory()->pc()->create();

        $busy = Component::factory()->ofCategory(ComponentCategory::Ram)->create([
            'status' => ComponentStatus::Installed,
        ]);

        \App\Models\ComponentInstallation::factory()->create([
            'component_id' => $busy->id,
            'asset_id' => $otherAsset->id,
            'removed_date' => null,
        ]);

        $this->actingAs($this->admin())->post(route('assets.store'), $this->assetPayload([
            'components' => ['stock' => [$busy->id]],
        ]))->assertSessionHasErrors('components.stock.0');

        // Aset baru TIDAK dibuat; satu-satunya PC tetap host lama.
        $this->assertSame(1, Asset::where('type', 'PC')->count());
        $this->assertTrue(Asset::where('type', 'PC')->sole()->is($otherAsset));
    }

    /**
     * Validasi gagal → tidak ada aset/komponen setengah jadi (transaksi).
     */
    public function test_failed_validation_leaves_no_partial_data(): void
    {
        $this->actingAs($this->admin())->post(route('assets.store'), $this->assetPayload([
            'mac_address' => '',
            'components' => [
                'new' => [['category' => 'ram', 'brand' => 'Kingston', 'specs' => []]],
            ],
        ]))->assertSessionHasErrors('mac_address');

        $this->assertSame(0, Asset::count());
        $this->assertSame(0, Component::count());
    }

    /**
     * Printer (perangkat departemen) tidak boleh merakit komponen.
     */
    public function test_non_computer_ignores_component_assembly(): void
    {
        $this->actingAs($this->admin())->post(route('assets.store'), [
            'type' => 'Printer',
            'brand' => 'Epson',
            'components' => [
                'new' => [['category' => 'ram', 'brand' => 'Kingston', 'specs' => []]],
            ],
        ])->assertRedirect();

        $this->assertSame(0, Component::count());
    }

    public function test_create_form_exposes_assembly_ui(): void
    {
        Component::factory()->ofCategory(ComponentCategory::Ram)->create(['brand' => 'Corsair']);

        $html = $this->actingAs($this->admin())
            ->get(route('assets.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Susun Komponen', $html);
        $this->assertStringContainsString('Komponen Baru', $html);
        $this->assertStringContainsString('Dari Gudang', $html);
        $this->assertStringContainsString('components[new][', $html);
        $this->assertStringContainsString('components[stock][]', $html);
    }
}
