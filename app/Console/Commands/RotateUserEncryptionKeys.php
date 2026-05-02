<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Key rotation command for pgcrypto-encrypted user PII.
 *
 * This command never runs automatically. Rotation is all-or-nothing: if any
 * batch fails, the transaction is rolled back and every row stays on the old key.
 */
class RotateUserEncryptionKeys extends Command
{
    protected $signature = 'users:rotate-keys
                                {--dry-run : Count affected rows without writing anything}
                                {--chunk=50 : Rows processed per UPDATE statement}
                                {--old-private-key-env=APP_OLD_PRIVATE_KEY : Environment variable holding the current private key}
                                {--new-public-key-env=APP_NEW_PUBLIC_KEY : Environment variable holding the new public key}
                                {--force : Skip the interactive confirmation prompt}';

    protected $description = 'Re-encrypt users.name and users.email with a new RSA public key (key rotation)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $force = (bool) $this->option('force');

        $total = DB::table('users')->count();

        $this->line('');
        $this->line('  <comment>Key Rotation Summary</comment>');
        $this->line('  Rows to rotate : <info>'.$total.'</info>');
        $this->line('  Mode           : '.($dryRun ? '<comment>DRY RUN - no writes</comment>' : '<error>LIVE - data will be re-encrypted</error>'));
        $this->line('');

        if ($dryRun) {
            $this->info('[DRY RUN] Would re-encrypt '.$total.' user row(s). No changes written.');

            return self::SUCCESS;
        }

        if (! $force && ! $this->confirm(
            'This will re-encrypt ALL '.$total.' users. '
            .'Keep the current APP_PRIVATE_KEY active until this command succeeds. Continue?'
        )) {
            $this->line('Aborted.');

            return self::SUCCESS;
        }

        try {
            $oldPriv = $this->readArmoredKeyFromEnv((string) $this->option('old-private-key-env'), 'old private key');
            $newPub = $this->readArmoredKeyFromEnv((string) $this->option('new-public-key-env'), 'new public key');
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $ids = DB::table('users')->orderBy('id')->pluck('id')->all();
        $processed = 0;
        $errors = 0;

        try {
            DB::transaction(function () use ($ids, $chunkSize, $oldPriv, $newPub, $total, &$processed): void {
                foreach (array_chunk($ids, $chunkSize) as $chunk) {
                    $placeholders = implode(',', array_fill(0, count($chunk), '?'));

                    // Pure SQL: decrypt with old key, then encrypt with new key.
                    // email_hash is a keyed lookup value and does not depend on
                    // the PGP keypair, so it is intentionally preserved.
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
                            updated_at = NOW()
                        WHERE id IN ($placeholders)
                    SQL, [
                        $oldPriv,
                        $newPub,
                        $oldPriv,
                        $newPub,
                        ...$chunk,
                    ]);

                    $processed += count($chunk);
                    $this->line(sprintf(
                        '  <info>ok</info>  batch of %d  (total: %d / %d)',
                        count($chunk),
                        $processed,
                        $total
                    ));
                }
            });
        } catch (Throwable $e) {
            $errors++;
            $this->error('  FAIL: '.$this->sanitizeMessage($e->getMessage()));
        }

        $this->newLine();
        $this->table(
            ['Total', 'Processed', 'Errors'],
            [[$total, $processed, $errors]]
        );

        if ($errors > 0) {
            $this->error('Rotation failed. The transaction was rolled back; rows remain on the old key.');

            return self::FAILURE;
        }

        $this->info('Key rotation complete.');
        $this->line('');
        $this->line('  <comment>Next steps:</comment>');
        $this->line('  1. Set APP_PUBLIC_KEY in Railway to the new public key (base64)');
        $this->line('  2. Set APP_PRIVATE_KEY in Railway to the new private key (base64)');
        $this->line('  3. Save the new keypair to 1Password');
        $this->line('  4. Revoke / delete the old private key from your keyring');
        $this->line('  5. Clear PHP config cache: php artisan config:clear');

        return self::SUCCESS;
    }

    private function readArmoredKeyFromEnv(string $envName, string $label): string
    {
        $value = getenv($envName);
        if (! is_string($value)) {
            $value = $_ENV[$envName] ?? $_SERVER[$envName] ?? null;
        }

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException("Missing {$label}. Set {$envName} to a base64 or armored PGP key.");
        }

        $value = trim($value);
        if (str_contains($value, '-----BEGIN PGP ')) {
            return $value;
        }

        $decoded = base64_decode($value, strict: true);
        if ($decoded === false || ! str_contains($decoded, '-----BEGIN PGP ')) {
            throw new RuntimeException("{$envName} must contain a base64-encoded or armored PGP key.");
        }

        return $decoded;
    }

    private function sanitizeMessage(string $message): string
    {
        return preg_replace(
            '/-----BEGIN PGP [A-Z ]+ KEY BLOCK-----.*?-----END PGP [A-Z ]+ KEY BLOCK-----/s',
            '[PGP KEY REDACTED]',
            $message
        ) ?? $message;
    }
}
