<?php

namespace Tests\Feature\Security;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that LIKE wildcard characters in search input are escaped
 * and cannot cause unintended SQL matching.
 */
class LikeInjectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
    }

    public function test_percent_wildcard_does_not_match_all_rows(): void
    {
        // Create two rows: one with literal % in name, one without
        ActivityLog::factory()->create(['user_name' => 'Alice%Percent']);
        ActivityLog::factory()->create(['user_name' => 'Bob Normal']);

        // Searching for % should only find the row with literal % in name
        $this->actingAs($this->admin)->get('/activity-logs?search=' . urlencode('%'))
            ->assertInertia(fn ($page) => $page->has('logs.data', 1));
    }

    public function test_underscore_wildcard_does_not_match_any_char(): void
    {
        ActivityLog::factory()->create(['user_name' => 'Alice_Under']);
        ActivityLog::factory()->create(['user_name' => 'Bob']); // should NOT match single-char wildcard

        $this->actingAs($this->admin)->get('/activity-logs?search=' . urlencode('_'))
            ->assertInertia(fn ($page) => $page->has('logs.data', 1));
    }

    public function test_backslash_in_search_does_not_break_query(): void
    {
        ActivityLog::factory()->create(['user_name' => 'Alice\\Test']);

        $this->actingAs($this->admin)
            ->get('/activity-logs?search=' . urlencode('\\'))
            ->assertOk(); // must not throw a DB error
    }
}
