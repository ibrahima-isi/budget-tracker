<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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

        DB::statement('DROP FUNCTION IF EXISTS create_user(TEXT, TEXT, TEXT, TEXT, TEXT, BOOLEAN)');
        DB::statement('DROP FUNCTION IF EXISTS read_user(BIGINT, TEXT)');
        DB::statement('DROP FUNCTION IF EXISTS search_by_email_hash(TEXT, TEXT)');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION create_user(
                p_name          TEXT,
                p_email         TEXT,
                p_password      TEXT,
                p_email_hash    TEXT,
                p_pub_key       TEXT,
                p_is_admin      BOOLEAN DEFAULT FALSE,
                p_is_approved   BOOLEAN DEFAULT FALSE
            )
            RETURNS BIGINT
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_id BIGINT;
            BEGIN
                INSERT INTO users (name, email, email_hash, password, is_admin, is_approved, approved_at, created_at, updated_at)
                VALUES (
                    pgp_pub_encrypt(p_name,  dearmor(p_pub_key)),
                    pgp_pub_encrypt(p_email, dearmor(p_pub_key)),
                    p_email_hash,
                    p_password,
                    p_is_admin,
                    p_is_approved,
                    CASE WHEN p_is_approved THEN NOW() ELSE NULL END,
                    NOW(),
                    NOW()
                )
                RETURNING id INTO v_id;

                RETURN v_id;
            END;
            $$
        SQL);

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
                is_approved       BOOLEAN,
                approved_at       TIMESTAMP,
                approved_by       BIGINT,
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
                    u.is_approved,
                    u.approved_at,
                    u.approved_by,
                    u.created_at,
                    u.updated_at
                FROM users u
                WHERE u.id = p_id;
            END;
            $$
        SQL);

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
                is_approved       BOOLEAN,
                approved_at       TIMESTAMP,
                approved_by       BIGINT,
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
                    u.is_approved,
                    u.approved_at,
                    u.approved_by,
                    u.created_at,
                    u.updated_at
                FROM users u
                WHERE u.email_hash = p_hash;
            END;
            $$
        SQL);
    }

    public function down(): void
    {
        if (! $this->isPostgres()) {
            return;
        }

        DB::statement('DROP FUNCTION IF EXISTS search_by_email_hash(TEXT, TEXT)');
        DB::statement('DROP FUNCTION IF EXISTS read_user(BIGINT, TEXT)');
        DB::statement('DROP FUNCTION IF EXISTS create_user(TEXT, TEXT, TEXT, TEXT, TEXT, BOOLEAN, BOOLEAN)');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION create_user(
                p_name          TEXT,
                p_email         TEXT,
                p_password      TEXT,
                p_email_hash    TEXT,
                p_pub_key       TEXT,
                p_is_admin      BOOLEAN DEFAULT FALSE
            )
            RETURNS BIGINT
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_id BIGINT;
            BEGIN
                INSERT INTO users (name, email, email_hash, password, is_admin, created_at, updated_at)
                VALUES (
                    pgp_pub_encrypt(p_name,  dearmor(p_pub_key)),
                    pgp_pub_encrypt(p_email, dearmor(p_pub_key)),
                    p_email_hash,
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
    }
};
