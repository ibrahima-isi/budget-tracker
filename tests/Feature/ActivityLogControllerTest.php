<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $regular;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin   = User::factory()->create(['is_admin' => true,  'email_verified_at' => now()]);
        $this->regular = User::factory()->create(['is_admin' => false, 'email_verified_at' => now()]);
    }

    // ── Access control ─────────────────────────────────────────────────────────

    public function test_guest_cannot_access_activity_logs(): void
    {
        $this->get('/activity-logs')->assertRedirect('/login');
    }

    public function test_regular_user_cannot_access_activity_logs(): void
    {
        $this->actingAs($this->regular)->get('/activity-logs')->assertForbidden();
    }

    public function test_admin_can_access_activity_logs(): void
    {
        $this->actingAs($this->admin)->get('/activity-logs')->assertOk();
    }

    // ── Inertia component ──────────────────────────────────────────────────────

    public function test_renders_correct_inertia_component(): void
    {
        $this->actingAs($this->admin)->get('/activity-logs')
            ->assertInertia(fn ($page) => $page->component('ActivityLogs/Index'));
    }

    public function test_passes_required_props(): void
    {
        $this->actingAs($this->admin)->get('/activity-logs')
            ->assertInertia(fn ($page) => $page
                ->has('logs')
                ->has('filters')
                ->has('eventColors')
                ->has('eventOptions')
                ->has('subjectOptions')
            );
    }

    // ── Pagination ─────────────────────────────────────────────────────────────

    public function test_returns_paginated_logs(): void
    {
        ActivityLog::factory()->count(5)->create();

        $this->actingAs($this->admin)->get('/activity-logs')
            ->assertInertia(fn ($page) => $page->has('logs.data'));
    }

    // ── Filters ────────────────────────────────────────────────────────────────

    public function test_filters_by_event(): void
    {
        ActivityLog::factory()->create(['event' => 'created']);
        ActivityLog::factory()->create(['event' => 'deleted']);

        $this->actingAs($this->admin)->get('/activity-logs?event=created')
            ->assertInertia(fn ($page) => $page
                ->has('logs.data', 1)
                ->where('logs.data.0.event', 'created')
            );
    }

    public function test_filters_by_subject_type(): void
    {
        ActivityLog::factory()->create(['subject_type' => 'Budget']);
        ActivityLog::factory()->create(['subject_type' => 'Revenu']);

        $this->actingAs($this->admin)->get('/activity-logs?subject_type=Budget')
            ->assertInertia(fn ($page) => $page->has('logs.data', 1));
    }

    public function test_search_by_user_name(): void
    {
        ActivityLog::factory()->create(['user_name' => 'Alice Dupont']);
        ActivityLog::factory()->create(['user_name' => 'Bob Martin']);

        $this->actingAs($this->admin)->get('/activity-logs?search=Alice')
            ->assertInertia(fn ($page) => $page->has('logs.data', 1));
    }

    public function test_search_escapes_like_wildcards(): void
    {
        ActivityLog::factory()->create(['user_name' => 'Alice%Test']);
        ActivityLog::factory()->create(['user_name' => 'Unrelated']);

        // Search for literal '%' — should not match 'Unrelated'
        $this->actingAs($this->admin)->get('/activity-logs?search=' . urlencode('%'))
            ->assertInertia(fn ($page) => $page->has('logs.data', 1));
    }

    public function test_event_options_contains_all_standard_events(): void
    {
        $this->actingAs($this->admin)->get('/activity-logs')
            ->assertInertia(fn ($page) => $page
                ->where('eventOptions', array_keys(ActivityLog::$eventColors))
            );
    }

    public function test_empty_state_returns_no_logs(): void
    {
        $this->actingAs($this->admin)->get('/activity-logs')
            ->assertInertia(fn ($page) => $page->has('logs.data', 0));
    }
}
