<?php

namespace Tests\Feature;

use App\Models\Category;
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
        Category::factory()->count(4)->create();

        $this->actingAs($this->user)->get('/categories')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Categories/Index'));
    }

    public function test_index_includes_expenses_count(): void
    {
        Category::factory()->count(2)->create();

        $this->actingAs($this->user)->get('/categories')
            ->assertInertia(fn ($page) => $page->has('categories.0.expenses_count'));
    }

    // ── Store ──────────────────────────────────────────────────────────────────

    public function test_user_can_create_category(): void
    {
        $this->actingAs($this->user)->post('/categories', [
            'name'  => 'Alimentation',
            'color' => '#FF5733',
            'icon'  => 'shopping-cart',
        ])->assertRedirect('/categories');

        $this->assertDatabaseHas('categories', ['name' => 'Alimentation']);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->user)->post('/categories', [])
            ->assertSessionHasErrors(['name', 'color', 'icon']);
    }

    public function test_store_rejects_duplicate_name(): void
    {
        Category::factory()->create(['name' => 'Transport']);

        $this->actingAs($this->user)->post('/categories', [
            'name'  => 'Transport',
            'color' => '#000000',
            'icon'  => 'car',
        ])->assertSessionHasErrors(['name']);
    }

    public function test_store_rejects_invalid_hex_color(): void
    {
        $this->actingAs($this->user)->post('/categories', [
            'name'  => 'Test',
            'color' => 'red',        // not a hex color
            'icon'  => 'home',
        ])->assertSessionHasErrors(['color']);
    }

    public function test_store_rejects_short_hex_color(): void
    {
        $this->actingAs($this->user)->post('/categories', [
            'name'  => 'Test',
            'color' => '#FFF',       // 3-digit hex, not accepted
            'icon'  => 'home',
        ])->assertSessionHasErrors(['color']);
    }

    public function test_store_accepts_valid_hex_colors(): void
    {
        foreach (['#000000', '#FFFFFF', '#1a2b3c', '#ABC123'] as $i => $color) {
            $this->actingAs($this->user)->post('/categories', [
                'name'  => "Cat $i",
                'color' => $color,
                'icon'  => 'home',
            ])->assertRedirect('/categories');
        }
    }

    // ── Update ─────────────────────────────────────────────────────────────────

    public function test_user_can_update_category(): void
    {
        $category = Category::factory()->create(['name' => 'Old Name']);

        $this->actingAs($this->user)->patch("/categories/{$category->id}", [
            'name'  => 'New Name',
            'color' => $category->color,
            'icon'  => $category->icon,
        ])->assertRedirect('/categories');

        $this->assertEquals('New Name', $category->fresh()->name);
    }

    public function test_update_allows_same_name_for_the_same_category(): void
    {
        $category = Category::factory()->create(['name' => 'Transport']);

        // Updating with the same name should pass uniqueness check (exclude self)
        $this->actingAs($this->user)->patch("/categories/{$category->id}", [
            'name'  => 'Transport',
            'color' => '#123456',
            'icon'  => 'car',
        ])->assertRedirect('/categories');
    }

    public function test_update_rejects_name_already_taken_by_another_category(): void
    {
        $cat1 = Category::factory()->create(['name' => 'Alimentation']);
        $cat2 = Category::factory()->create(['name' => 'Transport']);

        $this->actingAs($this->user)->patch("/categories/{$cat2->id}", [
            'name'  => 'Alimentation',  // taken by $cat1
            'color' => '#000000',
            'icon'  => 'home',
        ])->assertSessionHasErrors(['name']);
    }

    public function test_update_validates_required_fields(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->user)->patch("/categories/{$category->id}", [])
            ->assertSessionHasErrors(['name', 'color', 'icon']);
    }

    public function test_update_returns_404_for_nonexistent_category(): void
    {
        $this->actingAs($this->user)->patch('/categories/99999', [
            'name' => 'Test', 'color' => '#000000', 'icon' => 'home',
        ])->assertNotFound();
    }

    // ── Destroy ────────────────────────────────────────────────────────────────

    public function test_user_can_delete_category(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->user)->delete("/categories/{$category->id}")
            ->assertRedirect('/categories');

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_delete_returns_404_for_nonexistent_category(): void
    {
        $this->actingAs($this->user)->delete('/categories/99999')->assertNotFound();
    }
}
