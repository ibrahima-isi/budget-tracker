<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorieTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['email_verified_at' => now()]);
    }

    // ── Access control ─────────────────────────────────────────────────────────

    public function test_guest_cannot_access_categories(): void
    {
        $this->get('/categories')->assertRedirect('/login');
    }

    public function test_unverified_user_cannot_access_categories(): void
    {
        $unverified = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($unverified)->get('/categories')->assertRedirect('/verify-email');
    }

    // ── Index ──────────────────────────────────────────────────────────────────

    public function test_user_can_list_categories(): void
    {
        Categorie::factory()->count(4)->create();

        $this->actingAs($this->user)->get('/categories')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Categories/Index'));
    }

    public function test_index_includes_depenses_count(): void
    {
        Categorie::factory()->count(2)->create();

        $this->actingAs($this->user)->get('/categories')
            ->assertInertia(fn ($page) => $page->has('categories.0.depenses_count'));
    }

    // ── Store ──────────────────────────────────────────────────────────────────

    public function test_user_can_create_categorie(): void
    {
        $this->actingAs($this->user)->post('/categories', [
            'nom'     => 'Alimentation',
            'couleur' => '#FF5733',
            'icone'   => 'shopping-cart',
        ])->assertRedirect('/categories');

        $this->assertDatabaseHas('categories', ['nom' => 'Alimentation']);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->user)->post('/categories', [])
            ->assertSessionHasErrors(['nom', 'couleur', 'icone']);
    }

    public function test_store_rejects_duplicate_nom(): void
    {
        Categorie::factory()->create(['nom' => 'Transport']);

        $this->actingAs($this->user)->post('/categories', [
            'nom'     => 'Transport',
            'couleur' => '#000000',
            'icone'   => 'car',
        ])->assertSessionHasErrors(['nom']);
    }

    public function test_store_rejects_invalid_hex_color(): void
    {
        $this->actingAs($this->user)->post('/categories', [
            'nom'     => 'Test',
            'couleur' => 'red',        // not a hex color
            'icone'   => 'home',
        ])->assertSessionHasErrors(['couleur']);
    }

    public function test_store_rejects_short_hex_color(): void
    {
        $this->actingAs($this->user)->post('/categories', [
            'nom'     => 'Test',
            'couleur' => '#FFF',       // 3-digit hex, not accepted
            'icone'   => 'home',
        ])->assertSessionHasErrors(['couleur']);
    }

    public function test_store_accepts_valid_hex_colors(): void
    {
        foreach (['#000000', '#FFFFFF', '#1a2b3c', '#ABC123'] as $i => $color) {
            $this->actingAs($this->user)->post('/categories', [
                'nom'     => "Cat $i",
                'couleur' => $color,
                'icone'   => 'home',
            ])->assertRedirect('/categories');
        }
    }

    // ── Update ─────────────────────────────────────────────────────────────────

    public function test_user_can_update_categorie(): void
    {
        $categorie = Categorie::factory()->create(['nom' => 'Vieux Nom']);

        $this->actingAs($this->user)->patch("/categories/{$categorie->id}", [
            'nom'     => 'Nouveau Nom',
            'couleur' => $categorie->couleur,
            'icone'   => $categorie->icone,
        ])->assertRedirect('/categories');

        $this->assertEquals('Nouveau Nom', $categorie->fresh()->nom);
    }

    public function test_update_allows_same_nom_for_the_same_categorie(): void
    {
        $categorie = Categorie::factory()->create(['nom' => 'Transport']);

        // Updating with the same name should pass uniqueness check (exclude self)
        $this->actingAs($this->user)->patch("/categories/{$categorie->id}", [
            'nom'     => 'Transport',
            'couleur' => '#123456',
            'icone'   => 'car',
        ])->assertRedirect('/categories');
    }

    public function test_update_rejects_nom_already_taken_by_another_categorie(): void
    {
        $cat1 = Categorie::factory()->create(['nom' => 'Alimentation']);
        $cat2 = Categorie::factory()->create(['nom' => 'Transport']);

        $this->actingAs($this->user)->patch("/categories/{$cat2->id}", [
            'nom'     => 'Alimentation',  // taken by $cat1
            'couleur' => '#000000',
            'icone'   => 'home',
        ])->assertSessionHasErrors(['nom']);
    }

    public function test_update_validates_required_fields(): void
    {
        $categorie = Categorie::factory()->create();

        $this->actingAs($this->user)->patch("/categories/{$categorie->id}", [])
            ->assertSessionHasErrors(['nom', 'couleur', 'icone']);
    }

    public function test_update_returns_404_for_nonexistent_categorie(): void
    {
        $this->actingAs($this->user)->patch('/categories/99999', [
            'nom' => 'Test', 'couleur' => '#000000', 'icone' => 'home',
        ])->assertNotFound();
    }

    // ── Destroy ────────────────────────────────────────────────────────────────

    public function test_user_can_delete_categorie(): void
    {
        $categorie = Categorie::factory()->create();

        $this->actingAs($this->user)->delete("/categories/{$categorie->id}")
            ->assertRedirect('/categories');

        $this->assertDatabaseMissing('categories', ['id' => $categorie->id]);
    }

    public function test_delete_returns_404_for_nonexistent_categorie(): void
    {
        $this->actingAs($this->user)->delete('/categories/99999')->assertNotFound();
    }
}
