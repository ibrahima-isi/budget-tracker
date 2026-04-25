<?php

declare(strict_types=1);

namespace Tests\Feature\Encryption;

use App\Models\User;
use App\Services\EncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Integration tests for the user encryption layer.
 *
 * These tests require:
 *  - A live PostgreSQL connection (Neon dev branch or local pg with pgcrypto)
 *  - APP_PUBLIC_KEY and APP_PRIVATE_KEY set in .env.testing
 *
 * They will be skipped automatically if keys are not configured.
 */
class UserEncryptionTest extends TestCase
{
    use RefreshDatabase;

    private EncryptionService $encryption;

    protected function setUp(): void
    {
        // Check BEFORE parent::setUp() so RefreshDatabase migrations are never
        // attempted on SQLite (which doesn't support pgcrypto extensions).
        // Run the full suite with:
        //   DB_CONNECTION=pgsql php artisan test --filter=UserEncryptionTest
        // and ensure APP_PUBLIC_KEY + APP_PRIVATE_KEY are set in .env.
        // getenv() works before parent::setUp() (app not yet booted).
        // config() would fail here because the container isn't initialized yet.
        $dbConnection = getenv('DB_CONNECTION') ?: ($_ENV['DB_CONNECTION'] ?? 'sqlite');
        if ($dbConnection !== 'pgsql') {
            $this->markTestSkipped(
                'UserEncryptionTest requires PostgreSQL with pgcrypto. '
                . 'Set DB_CONNECTION=pgsql and configure APP_PUBLIC_KEY / APP_PRIVATE_KEY.'
            );
        }

        parent::setUp();

        $this->encryption = app(EncryptionService::class);

        try {
            $this->encryption->publicKey();
            $this->encryption->privateKey();
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Encryption keys not configured: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // Create
    // ------------------------------------------------------------------

    public function test_create_encrypted_stores_bytea_in_db(): void
    {
        $user = User::createEncrypted([
            'name'     => 'Alice Dupont',
            'email'    => 'alice@example.com',
            'password' => bcrypt('secret'),
        ]);

        $this->assertNotNull($user->id);

        // Verify the raw column is NOT plaintext
        $raw = DB::selectOne('SELECT name, email FROM users WHERE id = ?', [$user->id]);

        $this->assertNotEquals('Alice Dupont', $raw->name);
        $this->assertNotEquals('alice@example.com', $raw->email);
    }

    public function test_create_encrypted_stores_email_hash(): void
    {
        $user = User::createEncrypted([
            'name'     => 'Bob Martin',
            'email'    => 'Bob@Example.COM',
            'password' => bcrypt('secret'),
        ]);

        $expectedHash = $this->encryption->emailHash('Bob@Example.COM');

        $raw = DB::selectOne('SELECT email_hash FROM users WHERE id = ?', [$user->id]);

        $this->assertSame($expectedHash, $raw->email_hash);
    }

    // ------------------------------------------------------------------
    // Read
    // ------------------------------------------------------------------

    public function test_find_decrypted_returns_plaintext_fields(): void
    {
        $created = User::createEncrypted([
            'name'     => 'Charlie Brown',
            'email'    => 'charlie@example.com',
            'password' => bcrypt('secret'),
        ]);

        $found = User::findDecrypted($created->id);

        $this->assertNotNull($found);
        $this->assertSame('Charlie Brown', $found->name);
        $this->assertSame('charlie@example.com', $found->email);
    }

    public function test_find_decrypted_returns_null_for_missing_id(): void
    {
        $this->assertNull(User::findDecrypted(999999));
    }

    // ------------------------------------------------------------------
    // Find by email
    // ------------------------------------------------------------------

    public function test_find_by_email_returns_correct_user(): void
    {
        User::createEncrypted([
            'name'     => 'Dana White',
            'email'    => 'dana@example.com',
            'password' => bcrypt('secret'),
        ]);

        $found = User::findByEmail('dana@example.com');

        $this->assertNotNull($found);
        $this->assertSame('dana@example.com', $found->email);
    }

    public function test_find_by_email_is_case_insensitive(): void
    {
        User::createEncrypted([
            'name'     => 'Eve Crypto',
            'email'    => 'eve@example.com',
            'password' => bcrypt('secret'),
        ]);

        $this->assertNotNull(User::findByEmail('EVE@EXAMPLE.COM'));
    }

    public function test_find_by_email_returns_null_for_unknown(): void
    {
        $this->assertNull(User::findByEmail('nobody@nowhere.com'));
    }

    // ------------------------------------------------------------------
    // Update
    // ------------------------------------------------------------------

    public function test_update_encrypted_re_encrypts_name(): void
    {
        $user = User::createEncrypted([
            'name'     => 'Frank Old',
            'email'    => 'frank@example.com',
            'password' => bcrypt('secret'),
        ]);

        $user->updateEncrypted(['name' => 'Frank New']);

        $refreshed = User::findDecrypted($user->id);

        $this->assertSame('Frank New', $refreshed->name);
        $this->assertSame('frank@example.com', $refreshed->email); // unchanged
    }

    public function test_update_encrypted_re_encrypts_email_and_updates_hash(): void
    {
        $user = User::createEncrypted([
            'name'     => 'Grace Hop',
            'email'    => 'grace@old.com',
            'password' => bcrypt('secret'),
        ]);

        $user->updateEncrypted(['email' => 'grace@new.com']);

        $refreshed    = User::findDecrypted($user->id);
        $expectedHash = $this->encryption->emailHash('grace@new.com');
        $rawHash      = DB::selectOne('SELECT email_hash FROM users WHERE id = ?', [$user->id])->email_hash;

        $this->assertSame('grace@new.com', $refreshed->email);
        $this->assertSame($expectedHash, $rawHash);
    }

    // ------------------------------------------------------------------
    // Auth::attempt compatibility
    // ------------------------------------------------------------------

    public function test_auth_attempt_succeeds_with_correct_credentials(): void
    {
        User::createEncrypted([
            'name'     => 'Henry Auth',
            'email'    => 'henry@example.com',
            'password' => bcrypt('my-password'),
        ]);

        $result = \Illuminate\Support\Facades\Auth::attempt([
            'email'    => 'henry@example.com',
            'password' => 'my-password',
        ]);

        $this->assertTrue($result);
    }

    public function test_auth_attempt_fails_with_wrong_password(): void
    {
        User::createEncrypted([
            'name'     => 'Iris Auth',
            'email'    => 'iris@example.com',
            'password' => bcrypt('correct-password'),
        ]);

        $result = \Illuminate\Support\Facades\Auth::attempt([
            'email'    => 'iris@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertFalse($result);
    }
}
