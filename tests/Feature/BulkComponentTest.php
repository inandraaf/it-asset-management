<?php

namespace Tests\Feature;

use App\Enums\ComponentCategory;
use App\Enums\ComponentStatus;
use App\Models\Asset;
use App\Models\Component;
use App\Models\ComponentInstallation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Operasi komponen massal (FB-6).
 *
 * @see dokumentasi/15-feedback-dan-tindak-lanjut.md FB-6
 */
class BulkComponentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    // ------------------------------------------------------- Akses

    public function test_guest_is_redirected_to_login(): void
    {
        $asset = Asset::factory()->create();

        $this->get(route('components.bulk-install.create', $asset))->assertRedirect(route('login'));
    }

    public function test_viewer_cannot_access_bulk_operations(): void
    {
        $asset = Asset::factory()->create();
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get(route('components.bulk-install.create', $asset))->assertForbidden();
        $this->actingAs($viewer)->get(route('components.bulk-remove.create', $asset))->assertForbidden();
        $this->actingAs($viewer)->get(route('components.bulk-move.create', $asset))->assertForbidden();
    }

    // ------------------------------------------------- Pasang massal

    public function test_admin_can_install_multiple_components_at_once(): void
    {
        $asset = Asset::factory()->create(['asset_code' => 'PC-RAKIT-01']);

        $a = Component::factory()->ofCategory(ComponentCategory::Ram)->create();
        $b = Component::factory()->ofCategory(ComponentCategory::Storage)->create();
        $c = Component::factory()->ofCategory(ComponentCategory::Cpu)->create();

        $this->actingAs($this->admin())
            ->post(route('components.bulk-install.store', $asset), [
                'items' => [$a->id, $b->id, $c->id],
                'date' => now()->format('Y-m-d'),
                'notes' => 'Rakit PC baru',
            ])
            ->assertRedirect(route('assets.show', $asset))
            ->assertSessionHas('success');

        foreach ([$a, $b, $c] as $component) {
            $this->assertSame(ComponentStatus::Installed, $component->fresh()->status);
        }

        $this->assertSame(3, ComponentInstallation::where('asset_id', $asset->id)->count());
    }

    public function test_bulk_install_requires_at_least_one_component(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('components.bulk-install.store', $asset), [
                'items' => [],
                'date' => now()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('items');
    }

    /**
     * Bila satu komponen gagal divalidasi, SELURUH batch dibatalkan.
     */
    public function test_bulk_install_rolls_back_entire_batch_on_failure(): void
    {
        $asset = Asset::factory()->create();

        $ok = Component::factory()->create(['status' => ComponentStatus::InStock]);
        $rusak = Component::factory()->create(['status' => ComponentStatus::InRepair]);

        try {
            $this->actingAs($this->admin())
                ->post(route('components.bulk-install.store', $asset), [
                    'items' => [$ok->id, $rusak->id],
                    'date' => now()->format('Y-m-d'),
                ]);
        } catch (\Throwable) {
            // validasi gagal
        }

        // Tidak ada yang terpasang, termasuk komponen yang valid.
        $this->assertSame(ComponentStatus::InStock, $ok->fresh()->status);
        $this->assertSame(0, ComponentInstallation::where('asset_id', $asset->id)->count());
    }

    // --------------------------------------------------- Lepas massal

    public function test_admin_can_remove_multiple_components_at_once(): void
    {
        $asset = Asset::factory()->create();
        $installations = collect();

        foreach (range(1, 3) as $_) {
            $component = Component::factory()->create(['status' => ComponentStatus::Installed]);
            $installations->push(ComponentInstallation::factory()->create([
                'component_id' => $component->id,
                'asset_id' => $asset->id,
                'installed_date' => now()->subDays(10)->format('Y-m-d'),
                'removed_date' => null,
            ]));
        }

        $this->actingAs($this->admin())
            ->post(route('components.bulk-remove.store', $asset), [
                'items' => $installations->pluck('id')->all(),
                'date' => now()->format('Y-m-d'),
            ])
            ->assertRedirect(route('assets.show', $asset))
            ->assertSessionHas('success');

        foreach ($installations as $installation) {
            $this->assertNotNull($installation->fresh()->removed_date);
            $this->assertSame(ComponentStatus::InStock, $installation->component->fresh()->status);
        }
    }

    // -------------------------------------------------- Pindah massal

    public function test_admin_can_move_multiple_components_at_once(): void
    {
        $from = Asset::factory()->create(['asset_code' => 'PC-A']);
        $to = Asset::factory()->create(['asset_code' => 'PC-B']);

        $installations = collect();

        foreach (range(1, 2) as $_) {
            $component = Component::factory()->create(['status' => ComponentStatus::Installed]);
            $installations->push(ComponentInstallation::factory()->create([
                'component_id' => $component->id,
                'asset_id' => $from->id,
                'installed_date' => now()->subDays(20)->format('Y-m-d'),
                'removed_date' => null,
            ]));
        }

        $this->actingAs($this->admin())
            ->post(route('components.bulk-move.store', $from), [
                'items' => $installations->pluck('id')->all(),
                'target_asset_id' => $to->id,
                'date' => now()->format('Y-m-d'),
            ])
            ->assertRedirect(route('assets.show', $to))
            ->assertSessionHas('success');

        // Setiap komponen: 2 baris riwayat, lokasi = host baru.
        foreach ($installations as $installation) {
            $component = $installation->component->fresh();
            $this->assertSame($to->id, $component->activeInstallation->asset_id);
            $this->assertSame(2, ComponentInstallation::where('component_id', $component->id)->count());
        }
    }

    public function test_bulk_move_requires_target_host(): void
    {
        $asset = Asset::factory()->create();
        $component = Component::factory()->create(['status' => ComponentStatus::Installed]);
        $installation = ComponentInstallation::factory()->create([
            'component_id' => $component->id,
            'asset_id' => $asset->id,
            'removed_date' => null,
        ]);

        $this->actingAs($this->admin())
            ->post(route('components.bulk-move.store', $asset), [
                'items' => [$installation->id],
                'date' => now()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('target_asset_id');
    }

    // -------------------------------------------------------- Tampilan

    public function test_asset_detail_offers_bulk_actions(): void
    {
        $asset = Asset::factory()->create();
        $component = Component::factory()->create(['status' => ComponentStatus::Installed]);

        ComponentInstallation::factory()->create([
            'component_id' => $component->id,
            'asset_id' => $asset->id,
            'removed_date' => null,
        ]);

        $this->actingAs($this->admin())
            ->get(route('assets.show', $asset))
            ->assertOk()
            ->assertSee('Pasang Massal')
            ->assertSee('Lepas Massal')
            ->assertSee('Pindah Massal');
    }

    public function test_bulk_install_form_lists_only_warehouse_components(): void
    {
        $asset = Asset::factory()->create();
        Component::factory()->create(['component_code' => 'RAM-2026-0001']);
        Component::factory()->create([
            'component_code' => 'RAM-2026-0002',
            'status' => ComponentStatus::Installed,
        ]);

        $this->actingAs($this->admin())
            ->get(route('components.bulk-install.create', $asset))
            ->assertOk()
            ->assertSee('RAM-2026-0001')
            ->assertDontSee('RAM-2026-0002');
    }
}
