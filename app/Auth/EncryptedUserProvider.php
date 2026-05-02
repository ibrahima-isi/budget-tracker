<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\User;
use App\Services\EncryptionService;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\DB;

/**
 * Replaces EloquentUserProvider for the encrypted users table.
 *
 * What changes vs. the default provider on PostgreSQL:
 *  - retrieveByCredentials()  → looks up by email_hash instead of plaintext email
 *  - retrieveById()           → calls User::findDecrypted() (SQL function)
 *  - retrieveByToken()        → queries remember_token column (not encrypted)
 *  - updateRememberToken()    → plain Eloquent update on the remember_token column
 *  - validateCredentials()    → unchanged (password is still bcrypt, not encrypted)
 *
 * On non-PostgreSQL drivers (e.g. SQLite used in tests), all methods fall back
 * to the standard EloquentUserProvider behaviour so existing tests keep passing.
 */
class EncryptedUserProvider extends EloquentUserProvider
{
    public function __construct(
        Hasher $hasher,
        string $model,
        private readonly EncryptionService $encryption,
    ) {
        parent::__construct($hasher, $model);
    }

    // ------------------------------------------------------------------
    // Internal helper
    // ------------------------------------------------------------------

    /**
     * Returns true when running on PostgreSQL with pgcrypto encryption enabled.
     * On other drivers (SQLite for tests), falls back to standard Eloquent.
     */
    private function isEncryptedMode(): bool
    {
        return User::usesEncryptedStorage();
    }

    // ------------------------------------------------------------------
    // Auth contract implementation
    // ------------------------------------------------------------------

    public function retrieveById($identifier): ?Authenticatable
    {
        if (! $this->isEncryptedMode()) {
            return parent::retrieveById($identifier);
        }

        return User::findDecrypted((int) $identifier);
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        if (! $this->isEncryptedMode()) {
            return parent::retrieveByToken($identifier, $token);
        }

        return User::findByRememberToken((int) $identifier, (string) $token);
    }

    /**
     * The remember_token column is NOT encrypted — plain update.
     */
    public function updateRememberToken(Authenticatable $user, $token): void
    {
        DB::table('users')
            ->where('id', $user->getAuthIdentifier())
            ->update(['remember_token' => $token]);
    }

    /**
     * Uses email_hash index on PostgreSQL; plain email lookup on SQLite (tests).
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (! $this->isEncryptedMode()) {
            return parent::retrieveByCredentials($credentials);
        }

        if (! isset($credentials['email'])) {
            return null;
        }

        return User::findByEmail($credentials['email']);
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return parent::validateCredentials($user, $credentials);
    }
}
