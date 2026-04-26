<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use App\Services\EncryptionService;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'email_hash',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_admin'          => 'boolean',
        ];
    }

    // ------------------------------------------------------------------
    // Encrypted CRUD — all go through PostgreSQL stored functions
    // ------------------------------------------------------------------

    /**
     * Create a new encrypted user.
     * Returns the hydrated User model.
     *
     * The boolean is passed as 'true'/'false' string so PostgreSQL can
     * resolve the function signature correctly (avoids integer/boolean mismatch).
     */
    public static function createEncrypted(array $attributes): self
    {
        $svc      = app(EncryptionService::class);
        $isAdmin  = ($attributes['is_admin'] ?? false) ? 'true' : 'false';

        try {
            $id = DB::selectOne(
                'SELECT create_user(?, ?, ?, ?, ?::boolean) AS id',
                [
                    $attributes['name'],
                    $attributes['email'],
                    $attributes['password'],
                    $svc->publicKey(),
                    $isAdmin,
                ]
            )->id;
        } catch (QueryException $e) {
            throw new RuntimeException(
                'Failed to create encrypted user: ' . self::sanitizeMessage($e->getMessage()),
                0,
                $e
            );
        }

        return self::findDecrypted((int) $id);
    }

    /**
     * Fetch and decrypt a single user by primary key.
     */
    public static function findDecrypted(int $id): ?self
    {
        $svc = app(EncryptionService::class);

        try {
            $row = DB::selectOne('SELECT * FROM read_user(?, ?)', [
                $id,
                $svc->privateKey(),
            ]);
        } catch (QueryException $e) {
            throw new RuntimeException(
                'Failed to decrypt user: ' . self::sanitizeMessage($e->getMessage()),
                0,
                $e
            );
        }

        return $row ? self::hydrateRow((array) $row) : null;
    }

    /**
     * Find a user by email address (uses SHA-256 email_hash index).
     */
    public static function findByEmail(string $email): ?self
    {
        $svc  = app(EncryptionService::class);
        $hash = $svc->emailHash($email);

        try {
            $row = DB::selectOne('SELECT * FROM search_by_email_hash(?, ?)', [
                $hash,
                $svc->privateKey(),
            ]);
        } catch (QueryException $e) {
            throw new RuntimeException(
                'Failed to search by email: ' . self::sanitizeMessage($e->getMessage()),
                0,
                $e
            );
        }

        return $row ? self::hydrateRow((array) $row) : null;
    }

    /**
     * Update name and/or email for an existing user (re-encrypts with public key).
     * Pass only the fields you want to change; others are left untouched.
     */
    public function updateEncrypted(array $attributes): bool
    {
        $svc = app(EncryptionService::class);

        try {
            DB::statement('SELECT update_user(?, ?, ?, ?)', [
                $this->id,
                $attributes['name']  ?? null,
                $attributes['email'] ?? null,
                $svc->publicKey(),
            ]);
        } catch (QueryException $e) {
            throw new RuntimeException(
                'Failed to update encrypted user: ' . self::sanitizeMessage($e->getMessage()),
                0,
                $e
            );
        }

        // Refresh in-memory attributes from DB
        $fresh = self::findDecrypted($this->id);
        if ($fresh) {
            $this->setRawAttributes($fresh->getAttributes());
        }

        // Update non-encrypted fields normally via Eloquent
        $plain = array_diff_key($attributes, array_flip(['name', 'email']));
        if (! empty($plain)) {
            parent::fill($plain)->save();
        }

        return true;
    }

    // ------------------------------------------------------------------
    // Accessors
    // ------------------------------------------------------------------

    /**
     * Return the decrypted name string.
     *
     * When the User is fetched via standard Eloquent (not findDecrypted),
     * the BYTEA column comes back as a PHP resource stream. We detect that
     * and return a safe placeholder so callers (ActivityLogger, logs, etc.)
     * never receive binary garbage or a PHP resource.
     */
    public function getNameAttribute(mixed $value): string
    {
        if (is_resource($value)) {
            return '[encrypted]';
        }

        return (string) ($value ?? '');
    }

    /**
     * Same guard for the email column.
     */
    public function getEmailAttribute(mixed $value): string
    {
        if (is_resource($value)) {
            return '[encrypted]';
        }

        return (string) ($value ?? '');
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    private static function hydrateRow(array $row): self
    {
        $user = new self();
        $user->exists = true;
        $user->setRawAttributes([
            'id'                => $row['id'],
            'name'              => $row['name'],
            'email'             => $row['email'],
            'email_hash'        => $row['email_hash'],
            'email_verified_at' => $row['email_verified_at'],
            'password'          => $row['password'],
            'remember_token'    => $row['remember_token'] ?? null,
            'is_admin'          => $row['is_admin'],
            'created_at'        => $row['created_at'],
            'updated_at'        => $row['updated_at'],
        ]);

        return $user;
    }

    /**
     * Strips PGP key material from QueryException messages before they are
     * re-thrown or logged. Keys are identified by the PEM-like armor header.
     */
    private static function sanitizeMessage(string $message): string
    {
        return preg_replace(
            '/-----BEGIN PGP [A-Z ]+ KEY BLOCK-----.*?-----END PGP [A-Z ]+ KEY BLOCK-----/s',
            '[PGP KEY REDACTED]',
            $message
        ) ?? $message;
    }

    // ------------------------------------------------------------------
    // Laravel Auth compatibility
    // ------------------------------------------------------------------

    public static function findForAuth(int $id): ?self
    {
        return self::findDecrypted($id);
    }

    public static function findByRememberToken(int $id, string $token): ?self
    {
        $row = DB::selectOne(
            'SELECT id FROM users WHERE id = ? AND remember_token = ?',
            [$id, $token]
        );

        return $row ? self::findDecrypted((int) $row->id) : null;
    }

    // ------------------------------------------------------------------
    // Notifications
    // ------------------------------------------------------------------

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    // ------------------------------------------------------------------
    // __debugInfo — prevent decrypted PII from appearing in logs/dumps
    // ------------------------------------------------------------------

    public function __debugInfo(): array
    {
        return [
            'id'       => $this->id,
            'is_admin' => $this->is_admin,
        ];
    }
}
