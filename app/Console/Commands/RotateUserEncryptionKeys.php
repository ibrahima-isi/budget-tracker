<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Key rotation command for pgcrypto-encrypted user PII.
 *
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │  THIS COMMAND NEVER RUNS AUTOMATICALLY.                                 │
 * │  It must be invoked explicitly:                                         │
 * │    php artisan users:rotate-keys                                        │
 * │  or in production:                                                      │
 * │    railway run php artisan users:rotate-keys                            │
 * │                                                                         │
 * │  BEFORE RUNNING:                                                        │
 * │  1. Replace the OLD_PRIVATE_KEY placeholder below with the current      │
 * │     private key (from 1Password vault "Development").                   │
 * │  2. Replace the NEW_PUBLIC_KEY placeholder with the new public key.     │
 * │  3. Run in --dry-run mode first to verify row counts.                   │
 * │  4. Schedule a maintenance window — users cannot log in while rows are  │
 * │     being rotated (or accept a brief period of mixed keys).             │
 * │  5. After a successful rotation, update APP_PUBLIC_KEY and             │
 * │     APP_PRIVATE_KEY in Railway / .env with the new keys.               │
 * └─────────────────────────────────────────────────────────────────────────┘
 */
class RotateUserEncryptionKeys extends Command
{
    protected $signature = 'users:rotate-keys
                                {--dry-run : Count affected rows without writing anything}
                                {--chunk=50 : Rows processed per UPDATE statement}
                                {--force : Skip the interactive confirmation prompt}';

    protected $description = 'Re-encrypt users.name and users.email with a new RSA public key (key rotation)';

    // ──────────────────────────────────────────────────────────────────────
    // PLACEHOLDER VALUES — replace before running
    // ──────────────────────────────────────────────────────────────────────

    /**
     * The CURRENT private key (used to DECRYPT existing data).
     * Replace with the real armored OpenPGP private key from 1Password.
     *
     * op item get xrs5dsanxdd3jqcemdewh2dtny --reveal --fields "Private Key (armored)"
     */
    private const OLD_PRIVATE_KEY = <<<'PGP_PLACEHOLDER'
-----BEGIN PGP PRIVATE KEY BLOCK-----

REPLACE_THIS_WITH_THE_CURRENT_PRIVATE_KEY_FROM_1PASSWORD
(retrieve with: op item get xrs5dsanxdd3jqcemdewh2dtny --reveal --fields "Private Key (armored)")

-----END PGP PRIVATE KEY BLOCK-----
PGP_PLACEHOLDER;

    /**
     * The NEW public key (used to RE-ENCRYPT the data).
     * Generate with:
     *   gpg --batch --passphrase '' --gen-key < keygen.conf
     *   gpg --armor --export encryption-v2@budgettrack.local
     */
    private const NEW_PUBLIC_KEY = <<<'PGP_PLACEHOLDER'
-----BEGIN PGP PUBLIC KEY BLOCK-----

REPLACE_THIS_WITH_THE_NEW_PUBLIC_KEY

-----END PGP PUBLIC KEY BLOCK-----
PGP_PLACEHOLDER;

    // ──────────────────────────────────────────────────────────────────────

    public function handle(): int
    {
        $this->assertPlaceholdersReplaced();

        $dryRun    = (bool) $this->option('dry-run');
        $chunkSize = (int)  $this->option('chunk');
        $force     = (bool) $this->option('force');

        $total = DB::table('users')->count();

        $this->line('');
        $this->line('  <comment>Key Rotation Summary</comment>');
        $this->line('  Rows to rotate : <info>' . $total . '</info>');
        $this->line('  Mode           : ' . ($dryRun ? '<comment>DRY RUN — no writes</comment>' : '<error>LIVE — data will be re-encrypted</error>'));
        $this->line('');

        if ($dryRun) {
            $this->info('[DRY RUN] Would re-encrypt ' . $total . ' user row(s). No changes written.');
            return self::SUCCESS;
        }

        if (! $force && ! $this->confirm(
            'This will re-encrypt ALL ' . $total . ' users. '
            . 'Ensure APP_PUBLIC_KEY is already updated in Railway. Continue?'
        )) {
            $this->line('Aborted.');
            return self::SUCCESS;
        }

        $oldPriv   = self::OLD_PRIVATE_KEY;
        $newPub    = self::NEW_PUBLIC_KEY;
        $processed = 0;
        $errors    = 0;

        do {
            $ids = DB::table('users')
                ->orderBy('id')
                ->offset($processed)
                ->limit($chunkSize)
                ->pluck('id')
                ->toArray();

            if (empty($ids)) {
                break;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            try {
                // Pure SQL: decrypt with old key → encrypt with new key.
                // Plaintext never leaves PostgreSQL.
                DB::statement(<<<SQL
                    UPDATE users
                    SET
                        name = pgp_pub_encrypt(
                            pgp_pub_decrypt(name, dearmor(?)),
                            dearmor(?)
                        ),
                        email = pgp_pub_encrypt(
                            pgp_pub_decrypt(email, dearmor(?)),
                            dearmor(?)
                        ),
                        email_hash = encode(
                            digest(
                                lower(pgp_pub_decrypt(email, dearmor(?))),
                                'sha256'
                            ),
                            'hex'
                        ),
                        updated_at = NOW()
                    WHERE id IN ($placeholders)
                SQL, [
                    $oldPriv, $newPub,   // name:  decrypt + encrypt
                    $oldPriv, $newPub,   // email: decrypt + encrypt
                    $oldPriv,            // email_hash: decrypt for hashing
                    ...$ids,
                ]);

                $processed += count($ids);
                $this->line(sprintf(
                    '  <info>ok</info>  batch of %d  (total: %d / %d)',
                    count($ids), $processed, $total
                ));

            } catch (Throwable $e) {
                $errors++;
                $sanitized = preg_replace(
                    '/-----BEGIN PGP [A-Z ]+ KEY BLOCK-----.*?-----END PGP [A-Z ]+ KEY BLOCK-----/s',
                    '[PGP KEY REDACTED]',
                    $e->getMessage()
                );
                $this->error('  FAIL: ' . $sanitized);
                break;
            }

        } while ($processed < $total);

        $this->newLine();
        $this->table(
            ['Total', 'Processed', 'Errors'],
            [[$total, $processed, $errors]]
        );

        if ($errors > 0) {
            $this->error('Rotation failed. Fix errors, then re-run.');
            $this->warn('The rows already processed use the NEW key; remaining rows still use the OLD key.');
            $this->warn('Keep BOTH keys available until rotation completes successfully.');
            return self::FAILURE;
        }

        $this->info('Key rotation complete.');
        $this->line('');
        $this->line('  <comment>Next steps:</comment>');
        $this->line('  1. Set APP_PUBLIC_KEY  in Railway to the new public key (base64)');
        $this->line('  2. Set APP_PRIVATE_KEY in Railway to the new private key (base64)');
        $this->line('  3. Save the new keypair to 1Password');
        $this->line('  4. Revoke / delete the old private key from your keyring');
        $this->line('  5. Clear PHP config cache: php artisan config:clear');

        return self::SUCCESS;
    }

    /**
     * Abort immediately if the developer forgot to replace the placeholder values.
     */
    private function assertPlaceholdersReplaced(): void
    {
        if (str_contains(self::OLD_PRIVATE_KEY, 'REPLACE_THIS')) {
            $this->error('OLD_PRIVATE_KEY is still a placeholder. Edit the command file and paste the real key.');
            exit(self::FAILURE);
        }

        if (str_contains(self::NEW_PUBLIC_KEY, 'REPLACE_THIS')) {
            $this->error('NEW_PUBLIC_KEY is still a placeholder. Edit the command file and paste the new public key.');
            exit(self::FAILURE);
        }
    }
}
