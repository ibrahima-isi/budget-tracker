<?php

namespace Tests\Unit\Services;

use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ActivityLoggerTest extends TestCase
{
    use RefreshDatabase;

    // ── labelFor ──────────────────────────────────────────────────────────────

    public function test_label_for_returns_label_first(): void
    {
        $budget = new Budget(['label' => 'My Budget', 'source' => 'ignored']);

        $this->assertEquals('My Budget', ActivityLogger::labelFor($budget));
    }

    public function test_label_for_falls_back_to_name(): void
    {
        $cat = new Category(['name' => 'Alimentation']);

        $this->assertEquals('Alimentation', ActivityLogger::labelFor($cat));
    }

    public function test_label_for_falls_back_to_primary_key_when_no_named_attr(): void
    {
        $user = User::factory()->make(['id' => 42]);

        $this->assertEquals('user#42', ActivityLogger::labelFor($user));
    }

    // ── sanitize ──────────────────────────────────────────────────────────────

    public function test_sanitize_removes_always_redact_fields(): void
    {
        $user = new User;
        $attrs = ['id' => 1, 'email' => 'a@b.com', 'password' => 'hashed', 'remember_token' => 'tok'];

        $result = ActivityLogger::sanitize($user, $attrs);

        $this->assertArrayNotHasKey('email', $result);
        $this->assertArrayNotHasKey('password', $result);
        $this->assertArrayNotHasKey('remember_token', $result);
    }

    public function test_sanitize_removes_user_pii_fields(): void
    {
        $user = new User; // User hides password and remember_token
        $attrs = ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com', 'password' => 'secret'];

        $result = ActivityLogger::sanitize($user, $attrs);

        $this->assertArrayNotHasKey('password', $result);
        $this->assertArrayNotHasKey('name', $result);
        $this->assertArrayNotHasKey('email', $result);
    }

    // ── snapshot ──────────────────────────────────────────────────────────────

    public function test_snapshot_returns_sanitized_attributes(): void
    {
        $cat = Category::factory()->create(['name' => 'Transport', 'color' => '#FF0000']);

        $snap = ActivityLogger::snapshot($cat);

        $this->assertArrayHasKey('name', $snap);
        $this->assertEquals('Transport', $snap['name']);
        $this->assertArrayNotHasKey('password', $snap);
    }

    // ── diff ──────────────────────────────────────────────────────────────────

    public function test_diff_returns_old_and_new_for_changed_fields(): void
    {
        $cat = Category::factory()->create(['name' => 'Old Name']);
        $cat->name = 'New Name';

        $diff = ActivityLogger::diff($cat);

        $this->assertArrayHasKey('old', $diff);
        $this->assertArrayHasKey('new', $diff);
        $this->assertEquals('Old Name', $diff['old']['name']);
        $this->assertEquals('New Name', $diff['new']['name']);
    }

    public function test_diff_returns_empty_array_when_nothing_changed(): void
    {
        $cat = Category::factory()->create(['name' => 'Same']);
        $diff = ActivityLogger::diff($cat); // no dirty fields after fresh create+sync

        $this->assertEmpty($diff);
    }

    // ── log ───────────────────────────────────────────────────────────────────

    public function test_log_persists_entry_to_database(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        $cat = Category::factory()->create(['name' => 'Courses']);

        ActivityLogger::log('created', $cat, ['new' => ['name' => 'Courses']]);

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'created',
            'subject_type' => 'Category',
            'subject_label' => 'Courses',
            'user_id' => $user->id,
            'user_name' => 'user#'.$user->id,
        ]);
    }

    public function test_log_records_null_subject_for_auth_events(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        ActivityLogger::log('login', $user);

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'login',
            'user_id' => $user->id,
            'user_name' => 'user#'.$user->id,
        ]);
    }

    public function test_log_works_without_authenticated_user(): void
    {
        $cat = Category::factory()->create();

        ActivityLogger::log('created', $cat);

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'created',
            'user_id' => null,
            'user_name' => null,
        ]);
    }
}
