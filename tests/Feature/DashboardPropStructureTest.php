<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Categorie;
use App\Models\Depense;
use App\Models\Revenu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests the nested mensuel/annuel prop structure used by the Vue dashboard
 * (in addition to the flat props tested in DashboardTest).
 */
class DashboardPropStructureTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['email_verified_at' => now()]);
    }

    public function test_mensuel_prop_contains_required_keys(): void
    {
        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->has('mensuel.totalBudget')
                ->has('mensuel.totalDepenses')
                ->has('mensuel.totalRevenus')
                ->has('mensuel.solde')
                ->has('mensuel.depensesParCategorie')
            );
    }

    public function test_annuel_prop_contains_required_keys(): void
    {
        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->has('annuel.totalBudget')
                ->has('annuel.totalDepenses')
                ->has('annuel.totalRevenus')
                ->has('annuel.solde')
                ->has('annuel.depensesParCategorie')
            );
    }

    public function test_mensuel_total_budget_sums_monthly_budgets(): void
    {
        Budget::factory()->mensuel()->create(['user_id' => $this->user->id, 'montant_prevu' => 100000]);
        Budget::factory()->mensuel()->create(['user_id' => $this->user->id, 'montant_prevu' => 50000]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('mensuel.totalBudget', 150000));
    }

    public function test_annuel_solde_equals_revenus_minus_depenses(): void
    {
        $budget = Budget::factory()->create(['user_id' => $this->user->id]);
        $cat    = Categorie::factory()->create();

        Revenu::factory()->create([
            'user_id'     => $this->user->id,
            'montant'     => 1200000,
            'mois'        => now()->month,
            'annee'       => now()->year,
            'date_revenu' => now()->format('Y-m-01'),
        ]);
        Depense::factory()->create([
            'user_id'      => $this->user->id,
            'budget_id'    => $budget->id,
            'categorie_id' => $cat->id,
            'montant'      => 300000,
            'date_depense' => now()->format('Y-m-10'),
        ]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('annuel.solde', 900000));
    }

    public function test_dernières_depenses_includes_categorie_relation(): void
    {
        $budget = Budget::factory()->create(['user_id' => $this->user->id]);
        $cat    = Categorie::factory()->create(['nom' => 'Alimentation']);

        Depense::factory()->create([
            'user_id'      => $this->user->id,
            'budget_id'    => $budget->id,
            'categorie_id' => $cat->id,
        ]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->has('dernieresDepenses', 1)
                ->where('dernieresDepenses.0.categorie.nom', 'Alimentation')
            );
    }
}
