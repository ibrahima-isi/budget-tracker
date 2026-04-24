<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Revenue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests that the ModelActivityObserver actually fires and records the correct
 * audit entries when Eloquent models are created, updated, and deleted.
 */
class ActivityLogObserverTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['email_verified_at' => now()]);
    }

    // ── Category ──────────────────────────────────────────────────────────────

    public function test_creating_category_logs_created_event(): void
    {
        $this->actingAs($this->user)->post('/categories', [
            'name' => 'Loisirs', 'color' => '#3b82f6', 'icon' => 'star',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'event'        => 'created',
            'subject_type' => 'Category',
        ]);
    }

    public function test_updating_category_logs_updated_event(): void
    {
        $cat = Category::factory()->create(['name' => 'Old Name']);

        $this->actingAs($this->user)->patch("/categories/{$cat->id}", [
            'name' => 'New Name', 'color' => '#000000', 'icon' => 'home',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'event'        => 'updated',
            'subject_type' => 'Category',
            'subject_id'   => $cat->id,
        ]);
    }

    public function test_deleting_category_logs_deleted_event(): void
    {
        $cat = Category::factory()->create();

        $this->actingAs($this->user)->delete("/categories/{$cat->id}");

        $this->assertDatabaseHas('activity_logs', [
            'event'        => 'deleted',
            'subject_type' => 'Category',
            'subject_id'   => $cat->id,
        ]);
    }

    // ── Budget ────────────────────────────────────────────────────────────────

    public function test_creating_budget_logs_created_event(): void
    {
        $this->actingAs($this->user)->post('/budgets', [
            'type' => 'mensuel', 'month' => 4, 'year' => 2026, 'planned_amount' => 100000,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'event'        => 'created',
            'subject_type' => 'Budget',
            'user_id'      => $this->user->id,
        ]);
    }

    public function test_deleting_budget_logs_deleted_event(): void
    {
        $budget = Budget::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->delete("/budgets/{$budget->id}");

        $this->assertDatabaseHas('activity_logs', [
            'event'        => 'deleted',
            'subject_type' => 'Budget',
            'subject_id'   => $budget->id,
        ]);
    }

    // ── Revenue ───────────────────────────────────────────────────────────────

    public function test_creating_revenue_logs_created_event(): void
    {
        $this->actingAs($this->user)->post('/revenues', [
            'source' => 'Salaire', 'amount' => 500000, 'revenue_date' => '2026-04-01',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'event'        => 'created',
            'subject_type' => 'Revenue',
        ]);
    }

    // ── Observer diff behaviour ────────────────────────────────────────────────

    public function test_update_log_contains_old_and_new_values(): void
    {
        $cat = Category::factory()->create(['name' => 'OldName']);

        $this->actingAs($this->user)->patch("/categories/{$cat->id}", [
            'name' => 'NewName', 'color' => '#123456', 'icon' => 'car',
        ]);

        $log = ActivityLog::where('event', 'updated')
            ->where('subject_type', 'Category')
            ->where('subject_id', $cat->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertArrayHasKey('old', $log->properties);
        $this->assertArrayHasKey('new', $log->properties);
        $this->assertEquals('OldName', $log->properties['old']['name']);
        $this->assertEquals('NewName', $log->properties['new']['name']);
    }

    public function test_update_with_no_changes_does_not_log(): void
    {
        $cat = Category::factory()->create(['name' => 'Same', 'color' => '#123456', 'icon' => 'car']);

        $initialCount = ActivityLog::count();

        // Patch with identical data
        $this->actingAs($this->user)->patch("/categories/{$cat->id}", [
            'name' => 'Same', 'color' => '#123456', 'icon' => 'car',
        ]);

        $this->assertEquals($initialCount, ActivityLog::count());
    }

    // ── Sensitive data redaction ───────────────────────────────────────────────

    public function test_password_is_never_logged_in_activity_properties(): void
    {
        $this->actingAs($this->user)->post('/categories', [
            'name' => 'Test', 'color' => '#ff0000', 'icon' => 'tag',
        ]);

        $logs = ActivityLog::all();
        foreach ($logs as $log) {
            if ($log->properties) {
                $flat = json_encode($log->properties);
                $this->assertStringNotContainsString('password', $flat);
                $this->assertStringNotContainsString('remember_token', $flat);
            }
        }
    }
}
