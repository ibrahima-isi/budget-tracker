<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Categorie;
use App\Models\Depense;
use App\Models\Revenu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['email_verified_at' => now()]);
    }

    // ── Access control ─────────────────────────────────────────────────────────

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_unverified_user_is_redirected_from_dashboard(): void
    {
        $unverified = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($unverified)->get('/dashboard')->assertRedirect('/verify-email');
    }

    public function test_authenticated_verified_user_can_access_dashboard(): void
    {
        $this->actingAs($this->user)->get('/dashboard')->assertOk();
    }

    // ── Inertia component and prop keys ───────────────────────────────────────

    public function test_dashboard_renders_correct_inertia_component(): void
    {
        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->component('Dashboard'));
    }

    public function test_dashboard_passes_all_required_prop_keys(): void
    {
        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->has('budgetMensuel')
                ->has('totalDepenses')
                ->has('totalRevenus')
                ->has('solde')
                ->has('depensesParCategorie')
                ->has('dernieresDepenses')
                ->has('mois')
                ->has('annee')
            );
    }

    // ── Empty state ────────────────────────────────────────────────────────────

    public function test_budget_mensuel_is_null_when_no_budget_for_current_month(): void
    {
        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('budgetMensuel', null));
    }

    public function test_totals_are_zero_when_user_has_no_data(): void
    {
        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('totalDepenses', 0)
                ->where('totalRevenus', 0)
                ->where('solde', 0)
            );
    }

    public function test_dernières_depenses_is_empty_when_user_has_no_data(): void
    {
        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->has('dernieresDepenses', 0));
    }

    // ── Budget mensuel lookup ──────────────────────────────────────────────────

    public function test_budget_mensuel_is_found_for_current_month(): void
    {
        $budget = Budget::factory()->mensuel()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('budgetMensuel.id', $budget->id)
            );
    }

    public function test_budget_mensuel_is_null_for_annuel_budget(): void
    {
        Budget::factory()->annuel()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('budgetMensuel', null));
    }

    public function test_budget_mensuel_not_shown_for_other_users_budget(): void
    {
        $other = User::factory()->create(['email_verified_at' => now()]);
        Budget::factory()->mensuel()->create(['user_id' => $other->id]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('budgetMensuel', null));
    }

    // ── Totals calculations ────────────────────────────────────────────────────

    public function test_total_depenses_sums_current_month_only(): void
    {
        $budget = Budget::factory()->mensuel()->create(['user_id' => $this->user->id]);
        $cat    = Categorie::factory()->create();

        // Current month
        Depense::factory()->create([
            'user_id'      => $this->user->id,
            'budget_id'    => $budget->id,
            'categorie_id' => $cat->id,
            'montant'      => 20000,
            'date_depense' => now()->format('Y-m-15'),
        ]);
        Depense::factory()->create([
            'user_id'      => $this->user->id,
            'budget_id'    => $budget->id,
            'categorie_id' => $cat->id,
            'montant'      => 30000,
            'date_depense' => now()->format('Y-m-10'),
        ]);

        // Previous month — must NOT be included
        Depense::factory()->create([
            'user_id'      => $this->user->id,
            'budget_id'    => $budget->id,
            'categorie_id' => $cat->id,
            'montant'      => 999999,
            'date_depense' => now()->subMonth()->format('Y-m-01'),
        ]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('totalDepenses', 50000));
    }

    public function test_total_revenus_sums_current_month_only(): void
    {
        Revenu::factory()->create([
            'user_id'     => $this->user->id,
            'montant'     => 300000,
            'mois'        => now()->month,
            'annee'       => now()->year,
            'date_revenu' => now()->format('Y-m-01'),
        ]);

        // Previous month — must NOT be included
        Revenu::factory()->create([
            'user_id'     => $this->user->id,
            'montant'     => 999999,
            'mois'        => now()->subMonth()->month,
            'annee'       => now()->subMonth()->year,
            'date_revenu' => now()->subMonth()->format('Y-m-01'),
        ]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('totalRevenus', 300000));
    }

    public function test_solde_equals_revenus_minus_depenses(): void
    {
        $budget = Budget::factory()->mensuel()->create(['user_id' => $this->user->id]);
        $cat    = Categorie::factory()->create();

        Revenu::factory()->create([
            'user_id'     => $this->user->id,
            'montant'     => 500000,
            'mois'        => now()->month,
            'annee'       => now()->year,
            'date_revenu' => now()->format('Y-m-01'),
        ]);
        Depense::factory()->create([
            'user_id'      => $this->user->id,
            'budget_id'    => $budget->id,
            'categorie_id' => $cat->id,
            'montant'      => 150000,
            'date_depense' => now()->format('Y-m-05'),
        ]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('solde', 350000));
    }

    public function test_depenses_par_categorie_groups_correctly(): void
    {
        $budget = Budget::factory()->mensuel()->create(['user_id' => $this->user->id]);
        $cat1   = Categorie::factory()->create();
        $cat2   = Categorie::factory()->create();

        Depense::factory()->create([
            'user_id' => $this->user->id, 'budget_id' => $budget->id,
            'categorie_id' => $cat1->id, 'montant' => 10000,
            'date_depense' => now()->format('Y-m-01'),
        ]);
        Depense::factory()->create([
            'user_id' => $this->user->id, 'budget_id' => $budget->id,
            'categorie_id' => $cat1->id, 'montant' => 5000,
            'date_depense' => now()->format('Y-m-02'),
        ]);
        Depense::factory()->create([
            'user_id' => $this->user->id, 'budget_id' => $budget->id,
            'categorie_id' => $cat2->id, 'montant' => 8000,
            'date_depense' => now()->format('Y-m-03'),
        ]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->has('depensesParCategorie', 2));
    }

    public function test_depenses_par_categorie_excludes_other_users_data(): void
    {
        $other       = User::factory()->create();
        $otherBudget = Budget::factory()->mensuel()->create(['user_id' => $other->id]);
        $cat         = Categorie::factory()->create();

        Depense::factory()->create([
            'user_id' => $other->id, 'budget_id' => $otherBudget->id,
            'categorie_id' => $cat->id, 'montant' => 50000,
            'date_depense' => now()->format('Y-m-01'),
        ]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->has('depensesParCategorie', 0));
    }

    // ── Dernières dépenses ─────────────────────────────────────────────────────

    public function test_dernières_depenses_returns_at_most_5(): void
    {
        $budget = Budget::factory()->mensuel()->create(['user_id' => $this->user->id]);
        $cat    = Categorie::factory()->create();

        Depense::factory()->count(8)->create([
            'user_id'      => $this->user->id,
            'budget_id'    => $budget->id,
            'categorie_id' => $cat->id,
        ]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->has('dernieresDepenses', 5));
    }

    public function test_dernières_depenses_only_shows_current_user_data(): void
    {
        $other       = User::factory()->create();
        $otherBudget = Budget::factory()->create(['user_id' => $other->id]);
        $cat         = Categorie::factory()->create();

        Depense::factory()->count(3)->create([
            'user_id' => $other->id, 'budget_id' => $otherBudget->id, 'categorie_id' => $cat->id,
        ]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->has('dernieresDepenses', 0));
    }

    public function test_mois_and_annee_are_current_date(): void
    {
        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('mois', now()->month)
                ->where('annee', now()->year)
            );
    }
}
