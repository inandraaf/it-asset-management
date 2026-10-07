<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\ComponentStatus;
use App\Models\Asset;
use App\Models\Component;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Standarisasi Bahasa Indonesia (FB-2).
 *
 * Nilai enum tetap Inggris (dipakai database), label tampilan Bahasa Indonesia.
 *
 * @see dokumentasi/15-feedback-dan-tindak-lanjut.md FB-2
 */
class BahasaIndonesiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_asset_status_labels_are_indonesian(): void
    {
        $this->assertSame('Tersedia', AssetStatus::Available->label());
        $this->assertSame('Terpakai', AssetStatus::Assigned->label());
        $this->assertSame('Diperbaiki', AssetStatus::InRepair->label());
        $this->assertSame('Dipensiunkan', AssetStatus::Retired->label());
    }

    public function test_component_status_labels_are_indonesian(): void
    {
        $this->assertSame('Di Gudang', ComponentStatus::InStock->label());
        $this->assertSame('Terpasang', ComponentStatus::Installed->label());
        $this->assertSame('Diperbaiki', ComponentStatus::InRepair->label());
        $this->assertSame('Dipensiunkan', ComponentStatus::Retired->label());
    }

    public function test_enum_values_stay_english_for_database(): void
    {
        // Nilai DB tidak berubah — hanya label tampilan.
        $this->assertSame('Available', AssetStatus::Available->value);
        $this->assertSame('In Stock', ComponentStatus::InStock->value);
    }

    public function test_asset_list_shows_indonesian_status_label(): void
    {
        Asset::factory()->create(['status' => AssetStatus::Available]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('assets.index'))
            ->assertOk()
            ->assertSee('Tersedia')
            ->assertDontSee('>Available<', false);
    }

    public function test_component_list_shows_indonesian_status_label(): void
    {
        Component::factory()->create(['status' => ComponentStatus::InStock]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('components.index'))
            ->assertOk()
            ->assertSee('Di Gudang');
    }

    public function test_status_filter_options_are_indonesian(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('assets.index'))
            ->assertOk()
            ->assertSee('Tersedia')
            ->assertSee('Diperbaiki')
            ->assertSee('Dipensiunkan');
    }

    public function test_action_terms_are_indonesian_on_asset_detail(): void
    {
        $asset = Asset::factory()->create(['status' => AssetStatus::Available]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('assets.show', $asset))
            ->assertOk()
            ->assertSee('Serahkan')
            ->assertDontSee('>Assign<', false);
    }
}
