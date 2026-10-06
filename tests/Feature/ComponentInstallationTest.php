<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\ComponentCategory;
use App\Enums\ComponentStatus;
use App\Models\Asset;
use App\Models\Component;
use App\Models\ComponentInstallation;
use App\Models\User;
use App\Services\ComponentAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pemasangan & pelepasan komponen.
 *
 * @see dokumentasi/14-manajemen-komponen.md
 */
class ComponentInstallationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    // ------------------------------------------------------------- Akses

    public function test_guest_is_redirected_to_login(): void
    {
        $asset = Asset::factory()->create();

        $this->get(route('components.install.create', $asset))->assertRedirect(route('login'));
    }

    public function test_viewer_cannot_install_or_remove(): void
    {
        $asset = Asset::factory()->create();
        $component = Component::factory()->create();
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get(route('components.install.create', $asset))->assertForbidden();
        $this->actingAs($viewer)->post(route('components.install.store', $asset), [
            'component_id' => $component->id,
            'installed_date' => now()->format('Y-m-d'),
        ])->assertForbidden();

        $this->assertSame(ComponentStatus::InStock, $component->fresh()->status);
        $this->assertDatabaseCount('component_installations', 0);
    }

    // ------------------------------------------ Pemasangan dari sisi komponen

    public function test_attach_page_renders_for_in_stock_component(): void
    {
        $component = Component::factory()->create(['status' => ComponentStatus::InStock]);
        Asset::factory()->create(['asset_code' => 'PC-TARGET-01']);

        $this->actingAs($this->admin())
            ->get(route('components.attach.create', $component))
            ->assertOk()
            ->assertSee('Pasang ke Host')
            ->assertSee('PC-TARGET-01');
    }

    public function test_attach_page_redirects_for_installed_component(): void
    {
        $component = Component::factory()->create(['status' => ComponentStatus::Installed]);
        $asset = Asset::factory()->create();

        ComponentInstallation::factory()->create([
            'component_id' => $component->id,
            'asset_id' => $asset->id,
            'removed_date' => null,
        ]);

        $this->actingAs($this->admin())
            ->get(route('components.attach.create', $component))
            ->assertRedirect(route('components.show', $component))
            ->assertSessionHas('error');
    }

    public function test_admin_can_attach_component_from_component_side(): void
    {
        $asset = Asset::factory()->create(['asset_code' => 'PC-ATTACH-01']);
        $component = Component::factory()->create(['status' => ComponentStatus::InStock]);

        $this->actingAs($this->admin())
            ->post(route('components.attach.store', $component), [
                'asset_id' => $asset->id,
                'installed_date' => now()->format('Y-m-d'),
            ])
            ->assertRedirect(route('components.show', $component))
            ->assertSessionHas('success');

        $this->assertSame(ComponentStatus::Installed, $component->fresh()->status);
        $this->assertSame($asset->id, $component->fresh()->activeInstallation->asset_id);
    }

    // -------------------------------------------------- AC-6: Pasang

    public function test_admin_can_install_component_into_host(): void
    {
        $asset = Asset::factory()->create(['asset_code' => 'PC-RND-01']);
        $component = Component::factory()->create(['status' => ComponentStatus::InStock]);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('components.install.store', $asset), [
                'component_id' => $component->id,
                'installed_date' => now()->format('Y-m-d'),
                'notes' => 'Upgrade RAM',
            ])
            ->assertRedirect(route('assets.show', $asset))
            ->assertSessionHas('success');

        $this->assertSame(ComponentStatus::Installed, $component->fresh()->status);

        $this->assertDatabaseHas('component_installations', [
            'component_id' => $component->id,
            'asset_id' => $asset->id,
            'removed_date' => null,
            'installed_by' => $admin->id,
            'notes' => 'Upgrade RAM',
        ]);
    }

    public function test_installed_component_appears_on_asset_detail(): void
    {
        $asset = Asset::factory()->create(['asset_code' => 'PC-RND-05']);
        $component = Component::factory()->ofCategory(ComponentCategory::Ram)->create([
            'component_code' => 'RAM-2026-0099',
        ]);

        ComponentInstallation::factory()->create([
            'component_id' => $component->id,
            'asset_id' => $asset->id,
            'removed_date' => null,
        ]);

        $this->actingAs($this->admin())
            ->get(route('assets.show', $asset))
            ->assertOk()
            ->assertSee('Komponen Terpasang')
            ->assertSee('RAM-2026-0099');
    }

    /**
     * Admin harus bisa melepas / memindahkan komponen langsung dari halaman
     * detail aset, tidak hanya dari halaman detail komponen.
     */
    public function test_asset_detail_offers_remove_and_move_actions(): void
    {
        $asset = Asset::factory()->create(['asset_code' => 'PC-ACT-01']);
        $component = Component::factory()->create(['component_code' => 'RAM-2026-0055']);

        ComponentInstallation::factory()->create([
            'component_id' => $component->id,
            'asset_id' => $asset->id,
            'removed_date' => null,
        ]);

        $response = $this->actingAs($this->admin())->get(route('assets.show', $asset));

        $response->assertOk();
        $response->assertSee('Lepas');
        $response->assertSee('Konfirmasi Lepas');
        $response->assertSee('Tanggal Lepas');
        // Tautan pindah menuju form pindah komponen.
        $response->assertSee(route('components.move.create', $component), false);
    }

    public function test_asset_detail_hides_remove_move_actions_from_viewer(): void
    {
        $asset = Asset::factory()->create();
        $component = Component::factory()->create();

        ComponentInstallation::factory()->create([
            'component_id' => $component->id,
            'asset_id' => $asset->id,
            'removed_date' => null,
        ]);

        $response = $this->actingAs(User::factory()->viewer()->create())
            ->get(route('assets.show', $asset));

        $response->assertOk();
        $response->assertDontSee('Konfirmasi Lepas');
        $response->assertDontSee(route('components.move.create', $component), false);
    }

    /**
     * Setelah dilepas, komponen muncul sebagai tersedia untuk dipasang ke
     * host lain (AC-8 jalur manual: lepas lalu pasang).
     */
    public function test_removed_component_can_be_installed_to_another_host(): void
    {
        $hostA = Asset::factory()->create(['asset_code' => 'PC-A']);
        $hostB = Asset::factory()->create(['asset_code' => 'PC-B']);
        $component = Component::factory()->create(['status' => ComponentStatus::Installed]);

        $installation = ComponentInstallation::factory()->create([
            'component_id' => $component->id,
            'asset_id' => $hostA->id,
            'installed_date' => now()->subDays(20)->format('Y-m-d'),
            'removed_date' => null,
        ]);

        $admin = $this->admin();

        // Lepas
        $this->actingAs($admin)->post(route('components.remove', $installation), [
            'removed_date' => now()->format('Y-m-d'),
        ])->assertSessionHas('success');

        $this->assertSame(ComponentStatus::InStock, $component->fresh()->status);

        // Komponen kini muncul di daftar komponen yang bisa dipasang.
        $this->actingAs($admin)
            ->get(route('components.install.create', $hostB))
            ->assertOk()
            ->assertSee($component->component_code);

        // Pasang ke host lain.
        $this->actingAs($admin)->post(route('components.install.store', $hostB), [
            'component_id' => $component->id,
            'installed_date' => now()->format('Y-m-d'),
        ])->assertSessionHas('success');

        $this->assertSame($hostB->id, $component->fresh()->activeInstallation->asset_id);
        $this->assertSame(2, ComponentInstallation::where('component_id', $component->id)->count());
    }

    public function test_component_not_in_stock_cannot_be_installed(): void
    {
        $asset = Asset::factory()->create();
        $component = Component::factory()->create(['status' => ComponentStatus::InRepair]);

        $this->actingAs($this->admin())
            ->post(route('components.install.store', $asset), [
                'component_id' => $component->id,
                'installed_date' => now()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('component_id');

        $this->assertDatabaseCount('component_installations', 0);
    }

    public function test_future_install_date_is_rejected(): void
    {
        $asset = Asset::factory()->create();
        $component = Component::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('components.install.store', $asset), [
                'component_id' => $component->id,
                'installed_date' => now()->addDay()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('installed_date');
    }

    public function test_install_date_cannot_precede_host_creation(): void
    {
        $asset = Asset::factory()->create();
        // Mundurkan created_at host agar tanggal lama jadi tidak valid.
        $asset->forceFill(['created_at' => now()->subDays(10)])->save();

        $component = Component::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('components.install.store', $asset), [
                'component_id' => $component->id,
                'installed_date' => now()->subDays(30)->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('installed_date');
    }

    // -------------------------------------------------- AC-7: Lepas

    public function test_admin_can_remove_component_from_host(): void
    {
        $asset = Asset::factory()->create();
        $component = Component::factory()->create(['status' => ComponentStatus::Installed]);
        $installation = ComponentInstallation::factory()->create([
            'component_id' => $component->id,
            'asset_id' => $asset->id,
            'installed_date' => now()->subDays(30)->format('Y-m-d'),
            'removed_date' => null,
        ]);

        $this->actingAs($this->admin())
            ->post(route('components.remove', $installation), [
                'removed_date' => now()->format('Y-m-d'),
                'notes' => 'Dilepas',
            ])
            ->assertRedirect(route('components.show', $component))
            ->assertSessionHas('success');

        $installation->refresh();
        $this->assertNotNull($installation->removed_date);
        $this->assertSame(ComponentStatus::InStock, $component->fresh()->status);
    }

    /**
     * Bila dilepas dari detail aset, redirect kembali ke aset itu.
     */
    public function test_remove_from_asset_page_redirects_back_to_that_asset(): void
    {
        $asset = Asset::factory()->create(['asset_code' => 'PC-HOME-01']);
        $component = Component::factory()->create(['status' => ComponentStatus::Installed]);

        $installation = ComponentInstallation::factory()->create([
            'component_id' => $component->id,
            'asset_id' => $asset->id,
            'installed_date' => now()->subDays(10)->format('Y-m-d'),
            'removed_date' => null,
        ]);

        $this->actingAs($this->admin())
            ->post(route('components.remove', $installation), [
                'removed_date' => now()->format('Y-m-d'),
                'from' => 'asset',
            ])
            ->assertRedirect(route('assets.show', $asset))
            ->assertSessionHas('success');

        $this->assertSame(ComponentStatus::InStock, $component->fresh()->status);
    }

    public function test_remove_without_from_redirects_to_component(): void
    {
        $asset = Asset::factory()->create();
        $component = Component::factory()->create(['status' => ComponentStatus::Installed]);

        $installation = ComponentInstallation::factory()->create([
            'component_id' => $component->id,
            'asset_id' => $asset->id,
            'installed_date' => now()->subDays(10)->format('Y-m-d'),
            'removed_date' => null,
        ]);

        $this->actingAs($this->admin())
            ->post(route('components.remove', $installation), [
                'removed_date' => now()->format('Y-m-d'),
            ])
            ->assertRedirect(route('components.show', $component));
    }

    public function test_remove_date_before_install_date_is_rejected(): void
    {
        $installation = ComponentInstallation::factory()->create([
            'installed_date' => now()->subDays(5)->format('Y-m-d'),
            'removed_date' => null,
        ]);

        $this->actingAs($this->admin())
            ->post(route('components.remove', $installation), [
                'removed_date' => now()->subDays(10)->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('removed_date');

        $this->assertNull($installation->fresh()->removed_date);
    }

    public function test_removing_twice_is_rejected(): void
    {
        $installation = ComponentInstallation::factory()->removed()->create();

        $this->actingAs($this->admin())
            ->post(route('components.remove', $installation), [
                'removed_date' => now()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('installation');
    }

    // ---------------------------------------- AC-8: Pindah antarmesin

    public function test_move_creates_two_history_rows(): void
    {
        $from = Asset::factory()->create(['asset_code' => 'PC-A']);
        $to = Asset::factory()->create(['asset_code' => 'PC-B']);
        $component = Component::factory()->create(['status' => ComponentStatus::Installed]);

        ComponentInstallation::factory()->create([
            'component_id' => $component->id,
            'asset_id' => $from->id,
            'installed_date' => now()->subDays(60)->format('Y-m-d'),
            'removed_date' => null,
        ]);

        $this->actingAs($this->admin())
            ->post(route('components.move.store', $component), [
                'asset_id' => $to->id,
                'move_date' => now()->format('Y-m-d'),
            ])
            ->assertRedirect(route('components.show', $component))
            ->assertSessionHas('success');

        // Dua baris riwayat.
        $this->assertSame(2, ComponentInstallation::where('component_id', $component->id)->count());

        // Baris lama ditutup.
        $old = ComponentInstallation::where('component_id', $component->id)
            ->where('asset_id', $from->id)->sole();
        $this->assertNotNull($old->removed_date);

        // Lokasi saat ini = host baru.
        $this->assertSame($to->id, $component->fresh()->activeInstallation->asset_id);
        $this->assertSame(ComponentStatus::Installed, $component->fresh()->status);
    }

    public function test_move_to_same_host_is_rejected(): void
    {
        $asset = Asset::factory()->create();
        $component = Component::factory()->create(['status' => ComponentStatus::Installed]);

        ComponentInstallation::factory()->create([
            'component_id' => $component->id,
            'asset_id' => $asset->id,
            'removed_date' => null,
        ]);

        $this->actingAs($this->admin())
            ->post(route('components.move.store', $component), [
                'asset_id' => $asset->id,
                'move_date' => now()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('asset_id');

        $this->assertSame(1, ComponentInstallation::where('component_id', $component->id)->count());
    }

    public function test_move_is_rejected_when_component_not_installed(): void
    {
        $asset = Asset::factory()->create();
        $component = Component::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('components.move.store', $component), [
                'asset_id' => $asset->id,
                'move_date' => now()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('component_id');
    }

    public function test_move_form_excludes_current_host(): void
    {
        $current = Asset::factory()->create(['asset_code' => 'PC-CURRENT']);
        $other = Asset::factory()->create(['asset_code' => 'PC-OTHER']);
        $component = Component::factory()->create(['status' => ComponentStatus::Installed]);

        ComponentInstallation::factory()->create([
            'component_id' => $component->id,
            'asset_id' => $current->id,
            'removed_date' => null,
        ]);

        $response = $this->actingAs($this->admin())->get(route('components.move.create', $component));

        $response->assertOk()->assertSee('PC-OTHER');
        $response->assertDontSee('value="'.$current->id.'"', false);
    }

    public function test_move_form_back_link_points_to_component_by_default(): void
    {
        $asset = Asset::factory()->create();
        $component = Component::factory()->create(['status' => ComponentStatus::Installed]);

        ComponentInstallation::factory()->create([
            'component_id' => $component->id,
            'asset_id' => $asset->id,
            'removed_date' => null,
        ]);

        $this->actingAs($this->admin())
            ->get(route('components.move.create', $component))
            ->assertOk()
            ->assertSee(route('components.show', $component), false);
    }

    /**
     * Saat dibuka dari detail aset, tombol Batal harus kembali ke aset itu,
     * bukan ke detail komponen.
     */
    public function test_move_form_back_link_follows_origin_asset(): void
    {
        $asset = Asset::factory()->create(['asset_code' => 'PC-ORIGIN-01']);
        $component = Component::factory()->create(['status' => ComponentStatus::Installed]);

        ComponentInstallation::factory()->create([
            'component_id' => $component->id,
            'asset_id' => $asset->id,
            'removed_date' => null,
        ]);

        $this->actingAs($this->admin())
            ->get(route('components.move.create', ['component' => $component->id, 'from' => 'asset']))
            ->assertOk()
            ->assertSee('href="'.route('assets.show', $asset).'"', false);
    }

    public function test_move_from_asset_page_redirects_back_to_that_asset(): void
    {
        $from = Asset::factory()->create(['asset_code' => 'PC-FROM-01']);
        $to = Asset::factory()->create(['asset_code' => 'PC-TO-02']);
        $component = Component::factory()->create(['status' => ComponentStatus::Installed]);

        ComponentInstallation::factory()->create([
            'component_id' => $component->id,
            'asset_id' => $from->id,
            'installed_date' => now()->subDays(30)->format('Y-m-d'),
            'removed_date' => null,
        ]);

        $this->actingAs($this->admin())
            ->post(route('components.move.store', $component), [
                'asset_id' => $to->id,
                'move_date' => now()->format('Y-m-d'),
                'from' => 'asset',
                'from_asset_id' => $from->id,
            ])
            ->assertRedirect(route('assets.show', $from))
            ->assertSessionHas('success');

        $this->assertSame($to->id, $component->fresh()->activeInstallation->asset_id);
    }

    // --------------------------------------- AC-9/AC-10: Proteksi

    public function test_component_already_installed_elsewhere_cannot_be_installed_again(): void
    {
        $hostA = Asset::factory()->create();
        $hostB = Asset::factory()->create();
        $component = Component::factory()->create(['status' => ComponentStatus::InStock]);

        // Komponen punya instalasi aktif tetapi statusnya tidak konsisten.
        ComponentInstallation::factory()->create([
            'component_id' => $component->id,
            'asset_id' => $hostA->id,
            'removed_date' => null,
        ]);

        $this->actingAs($this->admin())
            ->post(route('components.install.store', $hostB), [
                'component_id' => $component->id,
                'installed_date' => now()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('component_id');

        $this->assertSame(1, ComponentInstallation::where('component_id', $component->id)->count());
    }

    public function test_retired_component_cannot_be_installed(): void
    {
        $asset = Asset::factory()->create();
        $component = Component::factory()->create(['status' => ComponentStatus::Retired]);

        $this->actingAs($this->admin())
            ->post(route('components.install.store', $asset), [
                'component_id' => $component->id,
                'installed_date' => now()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('component_id');
    }

    // ------------------------------------- Konsistensi status & guard

    public function test_install_page_lists_only_installable_components(): void
    {
        $asset = Asset::factory()->create();
        Component::factory()->create(['component_code' => 'RAM-2026-0001']);
        Component::factory()->create([
            'component_code' => 'RAM-2026-0002',
            'status' => ComponentStatus::InRepair,
        ]);

        $this->actingAs($this->admin())
            ->get(route('components.install.create', $asset))
            ->assertOk()
            ->assertSee('RAM-2026-0001')
            ->assertDontSee('RAM-2026-0002');
    }

    public function test_asset_with_installed_component_cannot_be_hard_deleted(): void
    {
        // FK restrictOnDelete: hard delete host berkomponen harus gagal.
        $asset = Asset::factory()->create();
        $component = Component::factory()->create(['status' => ComponentStatus::Installed]);

        ComponentInstallation::factory()->create([
            'component_id' => $component->id,
            'asset_id' => $asset->id,
            'removed_date' => null,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $asset->forceDelete();
    }

    public function test_move_rolls_back_when_target_is_invalid(): void
    {
        $from = Asset::factory()->create();
        $component = Component::factory()->create(['status' => ComponentStatus::Installed]);
        $installation = ComponentInstallation::factory()->create([
            'component_id' => $component->id,
            'asset_id' => $from->id,
            'installed_date' => now()->subDays(30)->format('Y-m-d'),
            'removed_date' => null,
        ]);

        try {
            app(ComponentAllocationService::class)->move($component, Asset::factory()->make(), now(), null);
            $this->fail('Pemindahan seharusnya gagal.');
        } catch (\Throwable $e) {
            // diharapkan
        }

        $installation->refresh();
        $this->assertNull($installation->removed_date, 'Riwayat lama tidak boleh ikut ditutup.');
        $this->assertSame(ComponentStatus::Installed, $component->fresh()->status);
        $this->assertSame(1, ComponentInstallation::where('component_id', $component->id)->count());
    }

    public function test_asset_delete_is_blocked_when_components_installed(): void
    {
        $asset = Asset::factory()->create(['status' => AssetStatus::Available]);
        $component = Component::factory()->create(['status' => ComponentStatus::Installed]);

        ComponentInstallation::factory()->create([
            'component_id' => $component->id,
            'asset_id' => $asset->id,
            'removed_date' => null,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('assets.destroy', $asset))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('assets', ['id' => $asset->id]);
    }
}
