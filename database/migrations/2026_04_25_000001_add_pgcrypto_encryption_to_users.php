<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Step 1 of user encryption:
 *  - Activates pgcrypto
 *  - Converts name/email TEXT columns to BYTEA, preserving existing data as
 *    raw UTF-8 bytes (convert_to). Existing rows are NOT yet PGP-encrypted;
 *    run `php artisan users:encrypt-existing` immediately after migrating.
 *  - Adds email_hash (NULL until EncryptExistingUsers fills it — used as the
 *    discriminator: rows with email_hash IS NULL have not been encrypted yet).
 *  - Replaces the unique index on email with a unique index on email_hash.
 *
 * down() restores the TEXT columns (decrypted values will be LOST — see ENCRYPTION.md).
 */
return new class extends Migration
{
    /**
     * Whether this migration should run.
     * Skips automatically on SQLite (used in unit/feature tests).
     */
    private function isPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    public function up(): void
    {
        if (! $this->isPostgres()) {
            return; // pgcrypto is PostgreSQL-only; skip for SQLite test suite
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto');

        // Drop unique constraint on plaintext email
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_email_unique');

        // Drop NOT NULL so the USING expression can be applied without rejecting NULLs
        DB::statement('ALTER TABLE users ALTER COLUMN name DROP NOT NULL');
        DB::statement('ALTER TABLE users ALTER COLUMN email DROP NOT NULL');

        // Convert TEXT → BYTEA, preserving existing plaintext as raw UTF-8 bytes.
        // Rows with email_hash IS NULL are identified as "not yet PGP-encrypted"
        // by the users:encrypt-existing command.
        DB::statement("ALTER TABLE users ALTER COLUMN name  TYPE BYTEA USING convert_to(name,  'UTF8')");
        DB::statement("ALTER TABLE users ALTER COLUMN email TYPE BYTEA USING convert_to(email, 'UTF8')");

        // email_hash: SHA-256 hex of lower(email). Filled by users:encrypt-existing.
        DB::statement('ALTER TABLE users ADD COLUMN IF NOT EXISTS email_hash TEXT');

        // Unique index on email_hash (partial: only rows that have been processed)
        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS users_email_hash_unique ON users (email_hash) '
            . 'WHERE email_hash IS NOT NULL'
        );
    }

    public function down(): void
    {
        if (! $this->isPostgres()) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS users_email_hash_unique');
        DB::statement('ALTER TABLE users DROP COLUMN IF EXISTS email_hash');

        // Restore columns as TEXT (existing encrypted BYTEA values become NULL)
        DB::statement('ALTER TABLE users ALTER COLUMN email TYPE TEXT USING NULL');
        DB::statement('ALTER TABLE users ALTER COLUMN name  TYPE TEXT USING NULL');

        DB::statement('ALTER TABLE users ALTER COLUMN name  SET NOT NULL');
        DB::statement('ALTER TABLE users ALTER COLUMN email SET NOT NULL');

        DB::statement('ALTER TABLE users ADD CONSTRAINT users_email_unique UNIQUE (email)');
    }
};
