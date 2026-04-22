<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Budget;
use App\Models\Categorie;
use App\Models\Depense;
use App\Models\Revenu;
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

    // ── Categorie ─────────────────────────────────────────────────────────────

    public function test_creating_categorie_logs_created_event(): void
    {
        $this->actingAs($this->user)->post('/categories', [
            'nom' => 'Loisirs', 'couleur' => '#3b82f6', 'icone' => 'star',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'event'        => 'created',
            'subject_type' => 'Categorie',
        ]);
    }

    public function test_updating_categorie_logs_updated_event(): void
    {
        $cat = Categorie::factory()->create(['nom' => 'Vieux']);

        $this->actingAs($this->user)->patch("/categories/{$cat->id}", [
            'nom' => 'Nouveau', 'couleur' => '#000000', 'icone' => 'home',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'event'        => 'updated',
            'subject_type' => 'Categorie',
            'subject_id'   => $cat->id,
        ]);
    }

    public function test_deleting_categorie_logs_deleted_event(): void
    {
        $cat = Categorie::factory()->create();

        $this->actingAs($this->user)->delete("/categories/{$cat->id}");

        $this->assertDatabaseHas('activity_logs', [
            'event'        => 'deleted',
            'subject_type' => 'Categorie',
            'subject_id'   => $cat->id,
        ]);
    }

    // ── Budget ────────────────────────────────────────────────────────────────

    public function test_creating_budget_logs_created_event(): void
    {
        $this->actingAs($this->user)->post('/budgets', [
            'type' => 'mensuel', 'mois' => 4, 'annee' => 2026, 'montant_prevu' => 100000,
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

    // ── Revenu ────────────────────────────────────────────────────────────────

    public function test_creating_revenu_logs_created_event(): void
    {
        $this->actingAs($this->user)->post('/revenus', [
            'source' => 'Salaire', 'montant' => 500000, 'date_revenu' => '2026-04-01',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'event'        => 'created',
            'subject_type' => 'Revenu',
        ]);
    }

    // ── Observer diff behaviour ────────────────────────────────────────────────

    public function test_update_log_contains_old_and_new_values(): void
    {
        $cat = Categorie::factory()->create(['nom' => 'OldName']);

        $this->actingAs($this->user)->patch("/categories/{$cat->id}", [
            'nom' => 'NewName', 'couleur' => '#123456', 'icone' => 'car',
        ]);

        $log = ActivityLog::where('event', 'updated')
            ->where('subject_type', 'Categorie')
            ->where('subject_id', $cat->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertArrayHasKey('old', $log->properties);
        $this->assertArrayHasKey('new', $log->properties);
        $this->assertEquals('OldName', $log->properties['old']['nom']);
        $this->assertEquals('NewName', $log->properties['new']['nom']);
    }

    public function test_update_with_no_changes_does_not_log(): void
    {
        $cat = Categorie::factory()->create(['nom' => 'Same', 'couleur' => '#123456', 'icone' => 'car']);

        $initialCount = ActivityLog::count();

        // Patch with identical data
        $this->actingAs($this->user)->patch("/categories/{$cat->id}", [
            'nom' => 'Same', 'couleur' => '#123456', 'icone' => 'car',
        ]);

        $this->assertEquals($initialCount, ActivityLog::count());
    }

    // ── Sensitive data redaction ───────────────────────────────────────────────

    public function test_password_is_never_logged_in_activity_properties(): void
    {
        $this->actingAs($this->user)->post('/categories', [
            'nom' => 'Test', 'couleur' => '#ff0000', 'icone' => 'tag',
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
