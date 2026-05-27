<?php

namespace Tests\Feature\Security;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Revenue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Verifies that protected routes enforce authentication, email verification, and approval.
 */
class RouteProtectionTest extends TestCase
{
    use RefreshDatabase;

    private User $unverified;

    protected function setUp(): void
    {
        parent::setUp();
        $this->unverified = User::factory()->create(['email_verified_at' => null]);
    }

    // ── Guest redirected to /login ─────────────────────────────────────────────

    #[DataProvider('protectedGetRoutesProvider')]
    public function test_guest_cannot_access_protected_get_route(string $route): void
    {
        $this->get($route)->assertRedirect('/login');
    }

    public static function protectedGetRoutesProvider(): array
    {
        return [
            'dashboard' => ['/dashboard'],
            'budgets' => ['/budgets'],
            'expenses' => ['/expenses'],
            'revenues' => ['/revenues'],
            'categories' => ['/categories'],
            'settings' => ['/settings'],
            'settings users' => ['/settings/users'],
            'profile' => ['/profile'],
        ];
    }

    #[DataProvider('protectedPostRoutesProvider')]
    public function test_guest_cannot_post_to_protected_route(string $route): void
    {
        $this->post($route)->assertRedirect('/login');
    }

    public static function protectedPostRoutesProvider(): array
    {
        return [
            'budgets' => ['/budgets'],
            'expenses' => ['/expenses'],
            'revenues' => ['/revenues'],
            'categories' => ['/categories'],
            'settings' => ['/settings'],
            'currencies' => ['/settings/currencies'],
        ];
    }

    public function test_guest_cannot_patch_budget(): void
    {
        $this->patch('/budgets/1')->assertRedirect('/login');
    }

    public function test_guest_cannot_delete_budget(): void
    {
        $this->delete('/budgets/1')->assertRedirect('/login');
    }

    public function test_guest_cannot_view_budget(): void
    {
        $this->get('/budgets/1')->assertRedirect('/login');
    }

    public function test_guest_cannot_patch_expense(): void
    {
        $this->patch('/expenses/1')->assertRedirect('/login');
    }

    public function test_guest_cannot_delete_expense(): void
    {
        $this->delete('/expenses/1')->assertRedirect('/login');
    }

    public function test_guest_cannot_patch_revenue(): void
    {
        $this->patch('/revenues/1')->assertRedirect('/login');
    }

    public function test_guest_cannot_delete_revenue(): void
    {
        $this->delete('/revenues/1')->assertRedirect('/login');
    }

    public function test_guest_cannot_patch_category(): void
    {
        $this->patch('/categories/1')->assertRedirect('/login');
    }

    public function test_guest_cannot_delete_category(): void
    {
        $this->delete('/categories/1')->assertRedirect('/login');
    }

    public function test_guest_cannot_delete_logo(): void
    {
        $this->delete('/settings/logo')->assertRedirect('/login');
    }

    public function test_guest_cannot_patch_currency_default(): void
    {
        $this->patch('/settings/currencies/1/default')->assertRedirect('/login');
    }

    public function test_guest_cannot_toggle_currency(): void
    {
        $this->patch('/settings/currencies/1/toggle')->assertRedirect('/login');
    }

    public function test_guest_cannot_delete_currency(): void
    {
        $this->delete('/settings/currencies/1')->assertRedirect('/login');
    }

    // ── Unverified user redirected to /verify-email ────────────────────────────

    #[DataProvider('verifiedGetRoutesProvider')]
    public function test_unverified_user_cannot_access_verified_route(string $route): void
    {
        $this->actingAs($this->unverified)->get($route)->assertRedirect('/verify-email');
    }

    public static function verifiedGetRoutesProvider(): array
    {
        return [
            'dashboard' => ['/dashboard'],
            'budgets' => ['/budgets'],
            'expenses' => ['/expenses'],
            'revenues' => ['/revenues'],
            'categories' => ['/categories'],
            'settings' => ['/settings'],
            'settings users' => ['/settings/users'],
            'profile' => ['/profile'],
        ];
    }

    #[DataProvider('verifiedPostRoutesProvider')]
    public function test_unverified_user_cannot_post_to_verified_route(string $route): void
    {
        $this->actingAs($this->unverified)->post($route)->assertRedirect('/verify-email');
    }

    public static function verifiedPostRoutesProvider(): array
    {
        return [
            'budgets' => ['/budgets'],
            'expenses' => ['/expenses'],
            'revenues' => ['/revenues'],
            'categories' => ['/categories'],
            'settings' => ['/settings'],
        ];
    }

    // ── Unapproved verified user redirected to /login ─────────────────────────

    #[DataProvider('approvedGetRoutesProvider')]
    public function test_unapproved_user_cannot_access_approved_route(string $route): void
    {
        $user = User::factory()->unapproved()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get($route)
            ->assertRedirect('/login')
            ->assertSessionHas('status');
    }

    public static function approvedGetRoutesProvider(): array
    {
        return [
            'dashboard' => ['/dashboard'],
            'budgets' => ['/budgets'],
            'expenses' => ['/expenses'],
            'revenues' => ['/revenues'],
            'categories' => ['/categories'],
            'settings' => ['/settings'],
            'profile' => ['/profile'],
        ];
    }

    public function test_unverified_user_cannot_delete_budget(): void
    {
        $budget = Budget::factory()->create(['user_id' => $this->unverified->id]);
        $this->actingAs($this->unverified)->delete("/budgets/{$budget->id}")->assertRedirect('/verify-email');
    }

    public function test_unverified_user_cannot_delete_revenue(): void
    {
        $revenue = Revenue::factory()->create(['user_id' => $this->unverified->id]);
        $this->actingAs($this->unverified)->delete("/revenues/{$revenue->id}")->assertRedirect('/verify-email');
    }

    public function test_unverified_user_cannot_delete_expense(): void
    {
        $cat = Category::factory()->create();
        $budget = Budget::factory()->create(['user_id' => $this->unverified->id]);
        $expense = Expense::factory()->create([
            'user_id' => $this->unverified->id, 'budget_id' => $budget->id, 'category_id' => $cat->id,
        ]);
        $this->actingAs($this->unverified)->delete("/expenses/{$expense->id}")->assertRedirect('/verify-email');
    }

    // ── Non-admin verified user blocked from settings (403) ──────────────────

    public function test_non_admin_user_cannot_access_settings(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'is_admin' => false]);
        $this->actingAs($user)->get('/settings')->assertForbidden();
    }

    public function test_non_admin_user_cannot_post_to_settings(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'is_admin' => false]);
        $this->actingAs($user)->post('/settings')->assertForbidden();
    }

    public function test_non_admin_user_cannot_manage_currencies(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'is_admin' => false]);
        $this->actingAs($user)->post('/settings/currencies')->assertForbidden();
    }

    // ── Public routes remain accessible ───────────────────────────────────────

    public function test_welcome_page_is_publicly_accessible(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_login_page_is_publicly_accessible(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_register_page_is_publicly_accessible(): void
    {
        $this->get('/register')->assertOk();
    }
}
