<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EncryptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Re-encrypts every row in the users table whose email_hash IS NULL.
 *
 * After the encryption migration, existing rows hold their original data as raw
 * UTF-8 bytes (BYTEA), NOT as PGP packets.  This command detects those rows via
 * `WHERE email_hash IS NULL`, using pgcrypto directly in PostgreSQL. The email
 * address is read by PHP only long enough to compute the keyed email_hash HMAC;
 * names are never read out of PostgreSQL by this command.
 *
 * Run order:
 *   1. php artisan migrate          ← applies the two encryption migrations
 *   2. php artisan users:encrypt-existing
 *
 * The command is idempotent: rows that already have email_hash set are skipped.
 */
class EncryptExistingUsers extends Command
{
    protected $signature = 'users:encrypt-existing
                                {--dry-run : Count affected rows without writing}
                                {--chunk=200 : Rows processed per UPDATE statement}';

    protected $description = 'PGP-encrypt plaintext name/email still stored as raw BYTEA in the users table';

    public function handle(EncryptionService $encryption): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');

        $pending = DB::table('users')->whereNull('email_hash')->count();

        if ($pending === 0) {
            $this->info('Nothing to do — all users already have email_hash set.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s%d user(s) need encryption.',
            $dryRun ? '[DRY RUN] ' : '',
            $pending
        ));

        if ($dryRun) {
            $this->table(['Pending rows'], [[$pending]]);

            return self::SUCCESS;
        }

        $publicKey = $encryption->publicKey();
        $processed = 0;
        $errors = 0;

        // Process in batches to avoid a single giant UPDATE locking the table
        do {
            $rows = DB::table('users')
                ->whereNull('email_hash')
                ->select('id')
                ->selectRaw("convert_from(email, 'UTF8') AS email")
                ->orderBy('id')
                ->limit($chunkSize)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            try {
                DB::transaction(function () use ($rows, $publicKey, $encryption): void {
                    foreach ($rows as $row) {
                        DB::statement(<<<'SQL'
                            UPDATE users
                            SET
                                name = pgp_pub_encrypt(
                                    convert_from(name, 'UTF8'),
                                    dearmor(?)
                                ),
                                email = pgp_pub_encrypt(
                                    convert_from(email, 'UTF8'),
                                    dearmor(?)
                                ),
                                email_hash = ?
                            WHERE id = ?
                              AND email_hash IS NULL
                        SQL, [
                            $publicKey,
                            $publicKey,
                            $encryption->emailHash((string) $row->email),
                            $row->id,
                        ]);
                    }
                });

                $processed += $rows->count();
                $this->line(sprintf('  <info>ok</info> batch of %d (total: %d)', $rows->count(), $processed));
            } catch (Throwable $e) {
                $errors++;
                $this->error('  Batch failed: '.$this->sanitizeMessage($e->getMessage()));
                break;
            }

        } while (true);

        $this->newLine();
        $this->table(
            ['Processed', 'Errors'],
            [[$processed, $errors]]
        );

        if ($errors > 0) {
            $this->error('Encryption failed on one or more batches.');

            return self::FAILURE;
        }

        $this->info('All rows encrypted successfully.');

        return self::SUCCESS;
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
