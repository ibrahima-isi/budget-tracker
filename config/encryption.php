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
];
