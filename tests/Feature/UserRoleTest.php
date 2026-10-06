<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @see dokumentasi/04-autentikasi.md §3
 */
class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_default_user_is_viewer_not_admin(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UserRole::Viewer, $user->role);
        $this->assertFalse($user->isAdmin());
        $this->assertTrue($user->isViewer());
    }

    public function test_admin_state_creates_an_admin(): void
    {
        $user = User::factory()->admin()->create();

        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertTrue($user->isAdmin());
    }

    public function test_role_cannot_be_elevated_through_mass_assignment(): void
    {
        $user = User::create([
            'name' => 'Attacker',
            'email' => 'attacker@example.com',
            'password' => 'password',
            'role' => UserRole::Admin->value,
        ]);

        $this->assertSame(UserRole::Viewer, $user->fresh()->role);
        $this->assertFalse($user->fresh()->isAdmin());
    }

    public function test_assign_role_is_the_controlled_path(): void
    {
        $user = User::factory()->create();

        $user->assignRole(UserRole::Admin);

        $this->assertTrue($user->fresh()->isAdmin());
    }
}
