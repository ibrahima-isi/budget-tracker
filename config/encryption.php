<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Public key path (optional — takes precedence over APP_PUBLIC_KEY)
    |--------------------------------------------------------------------------
    | Path to the armored OpenPGP public key file on disk.
    | In production, prefer setting APP_PUBLIC_KEY (base64) in the environment
    | and leaving this empty so no key file needs to be deployed.
    */
    'public_key_path' => env('APP_PUBLIC_KEY_PATH', storage_path('keys/public.pgp')),

    /*
    |--------------------------------------------------------------------------
    | Public key (base64-encoded armored OpenPGP block)
    |--------------------------------------------------------------------------
    | base64 -w0 storage/keys/public.pgp
    */
    'public_key' => env('APP_PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Private key (base64-encoded armored OpenPGP block)
    |--------------------------------------------------------------------------
    | NEVER commit this value. Set it only in .env (local) or the platform's
    | secret manager (production).
    |
    | base64 -w0 storage/keys/private.pgp
    */
    'private_key' => env('APP_PRIVATE_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Email lookup hash key
    |--------------------------------------------------------------------------
    | Secret used to build deterministic HMAC indexes for encrypted emails.
    | APP_KEY is used as a fallback so existing deployments stay functional,
    | but production should set APP_EMAIL_HASH_KEY to an independent secret.
    */
    'email_hash_key' => env('APP_EMAIL_HASH_KEY', env('APP_KEY')),

    /*
    |--------------------------------------------------------------------------
    | PostgreSQL private-key transport guard
    |--------------------------------------------------------------------------
    | When enabled, APP_PRIVATE_KEY will only be handed to PostgreSQL over a
    | certificate-verified TLS connection. Disable only for local test DBs.
    */
    'require_verified_database_tls' => env('APP_ENCRYPTION_REQUIRE_VERIFIED_DB_TLS', true),
];
