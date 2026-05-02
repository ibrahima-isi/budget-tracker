<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Manages RSA/OpenPGP key loading and delegates encrypt/decrypt operations
 * to the pgcrypto extension running in PostgreSQL.
 *
 * Key loading strategy:
 *  - Public key : storage/keys/public.pgp  OR  APP_PUBLIC_KEY (base64)
 *  - Private key: APP_PRIVATE_KEY (base64) ONLY — never stored on disk in production
 *
 * Neither key is ever written to Laravel logs.
 */
class EncryptionService
{
    private ?string $publicKey = null;

    private ?string $privateKey = null;

    // ------------------------------------------------------------------
    // Public key
    // ------------------------------------------------------------------

    /**
     * Returns the armored OpenPGP public key.
     * Loads from disk first, falls back to APP_PUBLIC_KEY env (base64).
     *
     * @throws RuntimeException when no public key can be found.
     */
    public function publicKey(): string
    {
        if ($this->publicKey !== null) {
            return $this->publicKey;
        }

        $path = config('encryption.public_key_path',
            storage_path('keys/public.pgp'));

        if (file_exists($path)) {
            $this->publicKey = file_get_contents($path);

            return $this->publicKey;
        }

        $b64 = config('encryption.public_key');
        if ($b64) {
            $decoded = base64_decode($b64, strict: true);
            if ($decoded === false) {
                throw new RuntimeException(
                    'APP_PUBLIC_KEY is not valid base64.'
                );
            }
            $this->publicKey = $decoded;

            return $this->publicKey;
        }

        throw new RuntimeException(
            'No public key found. Set APP_PUBLIC_KEY_PATH or APP_PUBLIC_KEY in your .env.'
        );
    }

    // ------------------------------------------------------------------
    // Private key
    // ------------------------------------------------------------------

    /**
     * Returns the armored OpenPGP private key from APP_PRIVATE_KEY (base64).
     * Never reads from disk in production.
     *
     * @throws RuntimeException when APP_PRIVATE_KEY is not set.
     */
    public function privateKey(): string
    {
        $this->assertVerifiedDatabaseTransport();

        if ($this->privateKey !== null) {
            return $this->privateKey;
        }

        $b64 = config('encryption.private_key');
        if (! $b64) {
            throw new RuntimeException(
                'APP_PRIVATE_KEY is not set. Cannot decrypt user data.'
            );
        }

        $decoded = base64_decode($b64, strict: true);
        if ($decoded === false) {
            throw new RuntimeException(
                'APP_PRIVATE_KEY is not valid base64.'
            );
        }

        $this->privateKey = $decoded;

        return $this->privateKey;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Returns a keyed deterministic lookup hash for a lowercased email address.
     * Used for the unique email_hash index.
     */
    public function emailHash(string $email): string
    {
        return hash_hmac('sha256', $this->normalizeEmail($email), $this->emailHashKey());
    }

    /**
     * Legacy unsalted hash kept only for seamless one-time migration lookups.
     */
    public function legacyEmailHash(string $email): string
    {
        return hash('sha256', $this->normalizeEmail($email));
    }

    /**
     * Clears cached keys from memory (call after a decrypt batch).
     */
    public function flush(): void
    {
        $this->publicKey = null;
        $this->privateKey = null;
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function emailHashKey(): string
    {
        $key = config('encryption.email_hash_key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException(
                'APP_EMAIL_HASH_KEY or APP_KEY must be set before email_hash can be generated.'
            );
        }

        return $key;
    }

    private function assertVerifiedDatabaseTransport(): void
    {
        if (! (bool) config('encryption.require_verified_database_tls', true)) {
            return;
        }

        $connectionName = config('database.default');
        $connection = config("database.connections.{$connectionName}", []);

        if (($connection['driver'] ?? null) !== 'pgsql') {
            return;
        }

        $sslMode = strtolower((string) ($connection['sslmode'] ?? ''));
        if ($sslMode === 'verify-full') {
            return;
        }

        throw new RuntimeException(
            'Encrypted user reads require PostgreSQL DB_SSLMODE=verify-full before APP_PRIVATE_KEY can be sent to the database. '
            .'Set APP_ENCRYPTION_REQUIRE_VERIFIED_DB_TLS=false only for local test databases.'
        );
    }

    // ------------------------------------------------------------------
    // __debugInfo — prevent key material from leaking into dumps / logs
    // ------------------------------------------------------------------

    public function __debugInfo(): array
    {
        return [
            'public_key_loaded' => $this->publicKey !== null,
            'private_key_loaded' => $this->privateKey !== null,
        ];
    }
}
