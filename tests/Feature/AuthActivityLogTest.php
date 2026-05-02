<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests that auth events (login, logout, registered) are recorded
 * in the activity_logs table via LogAuthEvent listener.
 */
class AuthActivityLogTest extends TestCase
{
    use RefreshDatabase;

    // ── Login ─────────────────────────────────────────────────────────────────

    public function test_login_event_is_logged(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'login',
            'user_id' => $user->id,
        ]);
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    public function test_logout_event_is_logged(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post('/logout');

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'logout',
            'user_id' => $user->id,
        ]);
    }

    // ── Registration ──────────────────────────────────────────────────────────

    public function test_registration_event_is_logged(): void
    {
        $this->post('/register', [
            'name' => 'Nouveau User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // Auth context may not be set when the Registered event fires,
        // so we check event name and subject type rather than user_id.
        $log = ActivityLog::where('event', 'registered')->first();

        $this->assertNotNull($log);
        $this->assertSame('User', $log->subject_type);
        $this->assertStringStartsWith('user#', $log->subject_label);
    }

    // ── Log contents ──────────────────────────────────────────────────────────

    public function test_login_log_contains_redacted_user_reference(): void
    {
        $user = User::factory()->create(['name' => 'Ibrahima', 'password' => bcrypt('password')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'login',
            'user_name' => 'user#'.$user->id,
        ]);

        $this->assertDatabaseMissing('activity_logs', [
            'event' => 'login',
            'user_name' => 'Ibrahima',
        ]);
    }

    public function test_login_log_does_not_contain_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret']);

        $logs = ActivityLog::where('event', 'login')->get();
        $this->assertNotEmpty($logs, 'Login should have been logged');

        foreach ($logs as $log) {
            $encoded = json_encode($log->properties ?? []);
            $this->assertStringNotContainsString('secret', $encoded);
            $this->assertStringNotContainsString('password', $encoded);
        }
    }
}
