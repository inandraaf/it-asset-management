<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kredensial akses aset — jumlah bebas (S5).
 *
 * @see dokumentasi/15-feedback-dan-tindak-lanjut.md S5
 */
class AssetCredentialTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Admin',
            'username' => 'admin.local',
            'password' => 'RahasiaWindows123',
            'notes' => null,
        ], $overrides);
    }

    // -------------------------------------------------------- Enkripsi

    public function test_credential_password_is_encrypted_at_rest(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('assets.credentials.store', $asset), $this->payload())
            ->assertSessionHasNoErrors();

        $credential = AssetCredential::sole();

        // Terdekripsi di model...
        $this->assertSame('RahasiaWindows123', $credential->password);

        // ...tetapi TIDAK tersimpan sebagai teks biasa.
        $raw = DB::table('asset_credentials')->where('id', $credential->id)->first();
        $this->assertNotSame('RahasiaWindows123', $raw->password);
        $this->assertStringNotContainsString('RahasiaWindows', (string) $raw->password);
    }

    public function test_credential_password_is_hidden_from_serialization(): void
    {
        $credential = AssetCredential::factory()->create(['password' => 'Rahasia']);

        $this->assertArrayNotHasKey('password', $credential->toArray());
    }

    // ---------------------------------------------------- Beberapa akun

    public function test_asset_can_have_multiple_credentials(): void
    {
        $asset = Asset::factory()->create();
        $admin = $this->admin();

        // PC biasanya punya 2 akun: Admin dan User Biasa.
        $this->actingAs($admin)->post(route('assets.credentials.store', $asset), $this->payload([
            'label' => 'Admin', 'username' => 'admin.local',
        ]))->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('assets.credentials.store', $asset), $this->payload([
            'label' => 'User Biasa', 'username' => 'user.local', 'password' => 'RahasiaUser456',
        ]))->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('assets.credentials.store', $asset), $this->payload([
            'label' => 'VNC', 'username' => null, 'password' => 'RahasiaVnc789', 'notes' => 'Port 5900',
        ]))->assertSessionHasNoErrors();

        $asset->refresh();

        $this->assertCount(3, $asset->credentials);
        $this->assertTrue($asset->hasCredentials());
        $this->assertSame(
            ['Admin', 'User Biasa', 'VNC'],
            $asset->credentials->pluck('label')->sort()->values()->all()
        );
    }

    public function test_credential_can_have_no_password(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('assets.credentials.store', $asset), $this->payload(['password' => null]))
            ->assertSessionHasNoErrors();

        $this->assertNull(AssetCredential::sole()->password);
    }

    public function test_label_is_required(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('assets.credentials.store', $asset), $this->payload(['label' => '']))
            ->assertSessionHasErrors('label');
    }

    // ------------------------------------------------------------ Update

    public function test_blank_password_keeps_existing_value(): void
    {
        $asset = Asset::factory()->create();
        $credential = AssetCredential::factory()->create([
            'asset_id' => $asset->id,
            'label' => 'Admin',
            'password' => 'LamaRahasia',
        ]);

        $this->actingAs($this->admin())
            ->put(route('credentials.update', $credential), [
                'label' => 'Admin Utama',
                'username' => 'admin2',
                'password' => '',
                'notes' => null,
            ])
            ->assertSessionHasNoErrors();

        $credential->refresh();
        $this->assertSame('Admin Utama', $credential->label);
        $this->assertSame('admin2', $credential->username);
        $this->assertSame('LamaRahasia', $credential->password, 'Kata sandi lama tidak boleh terhapus.');
    }

    public function test_new_password_overwrites(): void
    {
        $credential = AssetCredential::factory()->create(['password' => 'LamaRahasia']);

        $this->actingAs($this->admin())
            ->put(route('credentials.update', $credential), [
                'label' => $credential->label,
                'username' => null,
                'password' => 'BaruRahasia',
                'notes' => null,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('BaruRahasia', $credential->fresh()->password);
    }

    // ------------------------------------------------------------- Hapus

    public function test_admin_can_delete_credential(): void
    {
        $asset = Asset::factory()->create();
        $credential = AssetCredential::factory()->create(['asset_id' => $asset->id]);

        $this->actingAs($this->admin())
            ->delete(route('credentials.destroy', $credential))
            ->assertRedirect(route('assets.show', $asset))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('asset_credentials', ['id' => $credential->id]);
    }

    public function test_deleting_asset_removes_its_credentials(): void
    {
        $asset = Asset::factory()->create();
        AssetCredential::factory()->count(2)->create(['asset_id' => $asset->id]);

        $asset->forceDelete();

        $this->assertSame(0, AssetCredential::where('asset_id', $asset->id)->count());
    }

    // ---------------------------------------------------------- Tampilan

    public function test_admin_sees_all_credentials_on_asset_detail(): void
    {
        $asset = Asset::factory()->create();
        AssetCredential::factory()->create([
            'asset_id' => $asset->id, 'label' => 'Admin', 'username' => 'admin.local',
        ]);
        AssetCredential::factory()->create([
            'asset_id' => $asset->id, 'label' => 'User Biasa', 'username' => 'user.local',
        ]);

        $this->actingAs($this->admin())
            ->get(route('assets.show', $asset))
            ->assertOk()
            ->assertSee('Akses Remote & Kredensial')
            ->assertSee('Admin')
            ->assertSee('admin.local')
            ->assertSee('User Biasa')
            ->assertSee('user.local');
    }

    /**
     * Viewer BOLEH melihat kredensial agar staf EDP yang didelegasikan dapat
     * mengeksekusi saat Admin IT tidak tersedia (U2).
     */
    public function test_viewer_can_see_credentials(): void
    {
        $asset = Asset::factory()->create();
        AssetCredential::factory()->create([
            'asset_id' => $asset->id, 'label' => 'Admin', 'username' => 'admin.local',
            'password' => 'RahasiaWindows123',
        ]);

        $response = $this->actingAs(User::factory()->viewer()->create())
            ->get(route('assets.show', $asset));

        $response->assertOk();
        $response->assertSee('Akses Remote & Kredensial');
        $response->assertSee('admin.local');
    }

    /**
     * Namun viewer TIDAK melihat tombol tulis (tambah/hapus).
     */
    public function test_viewer_sees_no_write_controls_on_credentials(): void
    {
        $asset = Asset::factory()->create();
        AssetCredential::factory()->create(['asset_id' => $asset->id, 'label' => 'Admin']);

        $html = $this->actingAs(User::factory()->viewer()->create())
            ->get(route('assets.show', $asset))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Tambah Kredensial', $html);
        $this->assertStringNotContainsString('Hapus Kredensial', $html);
    }

    public function test_viewer_cannot_create_or_delete_credentials(): void
    {
        $asset = Asset::factory()->create();
        $credential = AssetCredential::factory()->create(['asset_id' => $asset->id]);
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)
            ->post(route('assets.credentials.store', $asset), $this->payload())
            ->assertForbidden();

        $this->actingAs($viewer)
            ->delete(route('credentials.destroy', $credential))
            ->assertForbidden();

        $this->assertSame(1, AssetCredential::count());
    }
}
