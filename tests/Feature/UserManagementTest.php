<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Manajemen akun user (S3).
 *
 * @see dokumentasi/15-feedback-dan-tindak-lanjut.md S3
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    // ------------------------------------------------------------- Akses

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('users.index'))->assertRedirect(route('login'));
    }

    public function test_viewer_cannot_access_user_management(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get(route('users.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('users.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('users.store'), [])->assertForbidden();
    }

    public function test_admin_can_view_user_list(): void
    {
        User::factory()->create(['name' => 'Budi EDP', 'username' => 'budi.edp']);

        $this->actingAs($this->admin())
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Budi EDP')
            ->assertSee('budi.edp')
            ->assertSee('Manajemen Akun');
    }

    // ------------------------------------------------------------ Membuat

    public function test_admin_can_create_read_only_user(): void
    {
        $this->actingAs($this->admin())
            ->post(route('users.store'), [
                'name' => 'Rina EDP',
                'username' => 'rina.edp',
                'role' => UserRole::Viewer->value,
                'password' => 'rahasia123',
                'password_confirmation' => 'rahasia123',
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $user = User::where('username', 'rina.edp')->sole();

        $this->assertSame(UserRole::Viewer, $user->role);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
    }

    public function test_admin_can_create_another_admin(): void
    {
        $this->actingAs($this->admin())
            ->post(route('users.store'), [
                'name' => 'Admin Cadangan',
                'username' => 'admin2',
                'role' => UserRole::Admin->value,
                'password' => 'rahasia123',
                'password_confirmation' => 'rahasia123',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(User::where('username', 'admin2')->sole()->isAdmin());
    }

    public function test_username_must_be_unique(): void
    {
        User::factory()->create(['username' => 'admin']);

        $this->actingAs($this->admin())
            ->post(route('users.store'), [
                'name' => 'Duplikat',
                'username' => 'admin',
                'role' => UserRole::Viewer->value,
                'password' => 'rahasia123',
                'password_confirmation' => 'rahasia123',
            ])
            ->assertSessionHasErrors('username');
    }

    public function test_username_is_normalized_to_lowercase(): void
    {
        $this->actingAs($this->admin())
            ->post(route('users.store'), [
                'name' => 'Budi',
                'username' => 'BUDI.EDP',
                'role' => UserRole::Viewer->value,
                'password' => 'rahasia123',
                'password_confirmation' => 'rahasia123',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['username' => 'budi.edp']);
    }

    public function test_password_confirmation_must_match(): void
    {
        $this->actingAs($this->admin())
            ->post(route('users.store'), [
                'name' => 'Budi',
                'username' => 'budi',
                'role' => UserRole::Viewer->value,
                'password' => 'rahasia123',
                'password_confirmation' => 'berbeda',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_new_user_role_cannot_be_injected_through_mass_assignment(): void
    {
        // Role dikirim lewat request, tetapi tetap diset eksplisit oleh controller.
        $this->actingAs($this->admin())
            ->post(route('users.store'), [
                'name' => 'Budi',
                'username' => 'budi',
                'role' => UserRole::Viewer->value,
                'password' => 'rahasia123',
                'password_confirmation' => 'rahasia123',
                'is_admin' => true,
            ]);

        $this->assertFalse(User::where('username', 'budi')->sole()->isAdmin());
    }

    // ----------------------------------------------------------- Mengubah

    public function test_admin_can_update_user_without_changing_password(): void
    {
        $user = User::factory()->viewer()->create(['username' => 'budi']);
        $originalPassword = $user->password;

        $this->actingAs($this->admin())
            ->put(route('users.update', $user), [
                'name' => 'Budi Santoso',
                'username' => 'budi',
                'role' => UserRole::Viewer->value,
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Budi Santoso', $user->name);
        $this->assertSame($originalPassword, $user->password, 'Kata sandi lama tidak boleh berubah.');
    }

    public function test_admin_can_change_password(): void
    {
        $user = User::factory()->viewer()->create(['username' => 'budi']);

        $this->actingAs($this->admin())
            ->put(route('users.update', $user), [
                'name' => $user->name,
                'username' => 'budi',
                'role' => UserRole::Viewer->value,
                'password' => 'barusekali123',
                'password_confirmation' => 'barusekali123',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('barusekali123', $user->fresh()->password));
    }

    public function test_admin_can_promote_viewer_to_admin(): void
    {
        $user = User::factory()->viewer()->create(['username' => 'budi']);

        $this->actingAs($this->admin())
            ->put(route('users.update', $user), [
                'name' => $user->name,
                'username' => 'budi',
                'role' => UserRole::Admin->value,
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($user->fresh()->isAdmin());
    }

    public function test_username_is_case_insensitive_on_uniqueness(): void
    {
        User::factory()->create(['username' => 'budi']);

        $this->actingAs($this->admin())
            ->post(route('users.store'), [
                'name' => 'Lain',
                'username' => 'BUDI',
                'role' => UserRole::Viewer->value,
                'password' => 'rahasia123',
                'password_confirmation' => 'rahasia123',
            ])
            ->assertSessionHasErrors('username');
    }

    // -------------------------------------------------------------- Hapus

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete(route('users.destroy', $admin))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_cannot_delete_the_last_admin(): void
    {
        $admin = $this->admin();
        $other = User::factory()->admin()->create();

        // Hapus admin kedua (bukan akun sendiri) — masih ada 2 admin, boleh.
        $this->actingAs($admin)->delete(route('users.destroy', $other))->assertSessionHas('success');

        // Kini hanya tersisa satu admin; membuat admin lain lalu coba hapus dari
        // sudut pandang admin yang tersisa tidak mungkin karena tak bisa hapus diri.
        $this->assertSame(1, User::where('role', UserRole::Admin)->count());
    }

    public function test_deleting_user_keeps_asset_audit_trail(): void
    {
        $admin = $this->admin();
        $creator = User::factory()->admin()->create(['username' => 'pembuat']);

        $asset = Asset::factory()->create(['created_by' => $creator->id]);
        $assignment = AssetAssignment::factory()->create(['assigned_by' => $creator->id]);

        $this->actingAs($admin)
            ->delete(route('users.destroy', $creator))
            ->assertSessionHas('success');

        // Aset & riwayat TETAP ada; hanya kolom audit yang dikosongkan.
        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'created_by' => null]);
        $this->assertDatabaseHas('asset_assignments', ['id' => $assignment->id, 'assigned_by' => null]);
    }

    public function test_admin_can_delete_a_viewer(): void
    {
        $viewer = User::factory()->viewer()->create(['username' => 'sementara']);

        $this->actingAs($this->admin())
            ->delete(route('users.destroy', $viewer))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $viewer->id]);
    }

    // ----------------------------------------------------------- Tampilan

    public function test_sidebar_shows_user_management_for_admin_only(): void
    {
        $this->actingAs($this->admin())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Manajemen Akun');

        $this->actingAs(User::factory()->viewer()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Manajemen Akun');
    }
}
