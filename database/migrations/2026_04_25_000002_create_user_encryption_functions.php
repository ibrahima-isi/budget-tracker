<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Creates four PostgreSQL helper functions that handle PGP encryption/decryption
 * for the users table.
 *
 * Security model:
 *  - The public key is passed IN by the caller (PHP) at encrypt time.
 *  - The private key is passed IN by the caller (PHP) at decrypt time.
 *  - Neither key is ever stored in the database.
 *  - Neon enforces SSL on all connections, protecting the keys in transit.
 *
 * Timestamp types match the Breeze-generated users table:
 *   email_verified_at / created_at / updated_at → TIMESTAMP (without time zone)
 */
return new class extends Migration
{
    private function isPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    public function up(): void
    {
        if (! $this->isPostgres()) {
            return;
        }

        // ------------------------------------------------------------------
        // 1. create_user
        // ------------------------------------------------------------------
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION create_user(
                p_name          TEXT,
                p_email         TEXT,
                p_password      TEXT,
                p_pub_key       TEXT,
                p_is_admin      BOOLEAN DEFAULT FALSE
            )
            RETURNS BIGINT
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_id   BIGINT;
                v_hash TEXT;
            BEGIN
                v_hash := encode(digest(lower(p_email), 'sha256'), 'hex');

                INSERT INTO users (name, email, email_hash, password, is_admin, created_at, updated_at)
                VALUES (
                    pgp_pub_encrypt(p_name,  dearmor(p_pub_key)),
                    pgp_pub_encrypt(p_email, dearmor(p_pub_key)),
                    v_hash,
                    p_password,
                    p_is_admin,
                    NOW(),
                    NOW()
                )
                RETURNING id INTO v_id;

                RETURN v_id;
            END;
            $$
        SQL);

        // ------------------------------------------------------------------
        // 2. read_user — returns a single decrypted user row by primary key
        //    TIMESTAMP (without tz) matches the Breeze-generated schema.
        // ------------------------------------------------------------------
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION read_user(
                p_id        BIGINT,
                p_priv_key  TEXT
            )
            RETURNS TABLE(
                id                BIGINT,
                name              TEXT,
                email             TEXT,
                email_hash        TEXT,
                email_verified_at TIMESTAMP,
                password          TEXT,
                remember_token    TEXT,
                is_admin          BOOLEAN,
                created_at        TIMESTAMP,
                updated_at        TIMESTAMP
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RETURN QUERY
                SELECT
                    u.id,
                    pgp_pub_decrypt(u.name,  dearmor(p_priv_key))::TEXT,
                    pgp_pub_decrypt(u.email, dearmor(p_priv_key))::TEXT,
                    u.email_hash,
                    u.email_verified_at,
                    u.password::TEXT,
                    u.remember_token::TEXT,
                    u.is_admin,
                    u.created_at,
                    u.updated_at
                FROM users u
                WHERE u.id = p_id;
            END;
            $$
        SQL);

        // ------------------------------------------------------------------
        // 3. search_by_email_hash
        // ------------------------------------------------------------------
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION search_by_email_hash(
                p_hash      TEXT,
                p_priv_key  TEXT
            )
            RETURNS TABLE(
                id                BIGINT,
                name              TEXT,
                email             TEXT,
                email_hash        TEXT,
                email_verified_at TIMESTAMP,
                password          TEXT,
                remember_token    TEXT,
                is_admin          BOOLEAN,
                created_at        TIMESTAMP,
                updated_at        TIMESTAMP
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RETURN QUERY
                SELECT
                    u.id,
                    pgp_pub_decrypt(u.name,  dearmor(p_priv_key))::TEXT,
                    pgp_pub_decrypt(u.email, dearmor(p_priv_key))::TEXT,
                    u.email_hash,
                    u.email_verified_at,
                    u.password::TEXT,
                    u.remember_token::TEXT,
                    u.is_admin,
                    u.created_at,
                    u.updated_at
                FROM users u
                WHERE u.email_hash = p_hash;
            END;
            $$
        SQL);

        // ------------------------------------------------------------------
        // 4. update_user
        // ------------------------------------------------------------------
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION update_user(
                p_id        BIGINT,
                p_name      TEXT,
                p_email     TEXT,
                p_pub_key   TEXT
            )
            RETURNS VOID
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_hash TEXT;
            BEGIN
                IF p_email IS NOT NULL THEN
                    v_hash := encode(digest(lower(p_email), 'sha256'), 'hex');
                END IF;

                UPDATE users
                SET
                    name       = CASE WHEN p_name  IS NOT NULL
                                      THEN pgp_pub_encrypt(p_name,  dearmor(p_pub_key))
                                      ELSE name  END,
                    email      = CASE WHEN p_email IS NOT NULL
                                      THEN pgp_pub_encrypt(p_email, dearmor(p_pub_key))
                                      ELSE email END,
                    email_hash = CASE WHEN p_email IS NOT NULL THEN v_hash ELSE email_hash END,
                    updated_at = NOW()
                WHERE id = p_id;
            END;
            $$
        SQL);
    }

    public function down(): void
    {
        if (! $this->isPostgres()) {
            return;
        }

        DB::statement('DROP FUNCTION IF EXISTS update_user(BIGINT, TEXT, TEXT, TEXT)');
        DB::statement('DROP FUNCTION IF EXISTS search_by_email_hash(TEXT, TEXT)');
        DB::statement('DROP FUNCTION IF EXISTS read_user(BIGINT, TEXT)');
        DB::statement('DROP FUNCTION IF EXISTS create_user(TEXT, TEXT, TEXT, TEXT, BOOLEAN)');
    }
};
