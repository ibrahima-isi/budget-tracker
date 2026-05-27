<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_users_are_pending_approval_and_not_authenticated(): void
    {
        $this->post('/register', [
            'name' => 'Pending User',
            'email' => 'pending@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect('/login')
            ->assertSessionHas('status');

        $this->assertGuest();

        $user = User::where('email', 'pending@example.com')->first();

        $this->assertNotNull($user);
        $this->assertFalse($user->is_approved);
        $this->assertNull($user->approved_at);
    }

    public function test_unapproved_user_with_valid_credentials_cannot_log_in(): void
    {
        $user = User::factory()->unapproved()->create([
            'email' => 'not-approved@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_unapproved_authenticated_user_cannot_access_app_resources(): void
    {
        $user = User::factory()->unapproved()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/login')
            ->assertSessionHas('status');

        $this->assertGuest();
    }

    public function test_admin_can_approve_user(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'is_approved' => true,
            'approved_at' => now(),
        ]);

        $user = User::factory()->unapproved()->create();

        $this->actingAs($admin)
            ->patch(route('settings.users.approve', $user))
            ->assertRedirect(route('settings.users.index'));

        $user->refresh();

        $this->assertTrue($user->is_approved);
        $this->assertNotNull($user->approved_at);
        $this->assertSame($admin->id, $user->approved_by);
    }

    public function test_admin_can_revoke_user_approval_except_self(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'is_approved' => true,
            'approved_at' => now(),
        ]);

        $user = User::factory()->create([
            'is_approved' => true,
            'approved_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('settings.users.revoke-approval', $user))
            ->assertRedirect(route('settings.users.index'));

        $this->assertFalse($user->refresh()->is_approved);

        $this->actingAs($admin)
            ->patch(route('settings.users.revoke-approval', $admin))
            ->assertRedirect(route('settings.users.index'))
            ->assertSessionHas('error');

        $this->assertTrue($admin->refresh()->is_approved);
    }

    public function test_non_admin_cannot_manage_user_approvals(): void
    {
        $regular = User::factory()->create([
            'is_admin' => false,
            'is_approved' => true,
        ]);
        $pending = User::factory()->unapproved()->create();

        $this->actingAs($regular)->get(route('settings.users.index'))->assertForbidden();
        $this->actingAs($regular)->patch(route('settings.users.approve', $pending))->assertForbidden();
    }
}
