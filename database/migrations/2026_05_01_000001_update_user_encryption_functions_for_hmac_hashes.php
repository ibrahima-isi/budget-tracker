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

        DB::statement('DROP FUNCTION IF EXISTS create_user(TEXT, TEXT, TEXT, TEXT, BOOLEAN)');
        DB::statement('DROP FUNCTION IF EXISTS update_user(BIGINT, TEXT, TEXT, TEXT)');

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
            CREATE OR REPLACE FUNCTION update_user(
                p_id         BIGINT,
                p_name       TEXT,
                p_email      TEXT,
                p_email_hash TEXT,
                p_pub_key    TEXT
            )
            RETURNS VOID
            LANGUAGE plpgsql
            AS $$
            BEGIN
                UPDATE users
                SET
                    name       = CASE WHEN p_name  IS NOT NULL
                                      THEN pgp_pub_encrypt(p_name,  dearmor(p_pub_key))
                                      ELSE name  END,
                    email      = CASE WHEN p_email IS NOT NULL
                                      THEN pgp_pub_encrypt(p_email, dearmor(p_pub_key))
                                      ELSE email END,
                    email_hash = CASE WHEN p_email IS NOT NULL THEN p_email_hash ELSE email_hash END,
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

        DB::statement('DROP FUNCTION IF EXISTS update_user(BIGINT, TEXT, TEXT, TEXT, TEXT)');
        DB::statement('DROP FUNCTION IF EXISTS create_user(TEXT, TEXT, TEXT, TEXT, TEXT, BOOLEAN)');
    }
};
