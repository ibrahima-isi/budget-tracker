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
 * `WHERE email_hash IS NULL` and re-encrypts them in a single SQL UPDATE per
 * batch, using pgcrypto directly in PostgreSQL — no plaintext data is sent over
 * the wire.
 *
 * Run order:
 *   1. php artisan migrate          ← applies the two encryption migrations
 *   2. php artisan users:encrypt-existing
 *
 * The command is idempotent: rows that already have email_hash set are skipped.
 */
class EncryptExistingUsers extends Command
{
    protected $signature   = 'users:encrypt-existing
                                {--dry-run : Count affected rows without writing}
                                {--chunk=200 : Rows processed per UPDATE statement}';

    protected $description = 'PGP-encrypt plaintext name/email still stored as raw BYTEA in the users table';

    public function handle(EncryptionService $encryption): int
    {
        $dryRun    = (bool) $this->option('dry-run');
        $chunkSize = (int)  $this->option('chunk');

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
        $errors    = 0;

        // Process in batches to avoid a single giant UPDATE locking the table
        do {
            $ids = DB::table('users')
                ->whereNull('email_hash')
                ->orderBy('id')
                ->limit($chunkSize)
                ->pluck('id')
                ->toArray();

            if (empty($ids)) {
                break;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            try {
                // Pure SQL: read raw BYTEA → convert back to text → PGP-encrypt.
                // No plaintext data leaves PostgreSQL.
                DB::statement(<<<SQL
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
                        email_hash = encode(
                            digest(lower(convert_from(email, 'UTF8')), 'sha256'),
                            'hex'
                        )
                    WHERE id IN ($placeholders)
                      AND email_hash IS NULL
                SQL, array_merge([$publicKey, $publicKey], $ids));

                $processed += count($ids);
                $this->line(sprintf('  <info>ok</info> batch of %d (total: %d)', count($ids), $processed));
            } catch (Throwable $e) {
                $errors++;
                $this->error('  Batch failed: ' . $e->getMessage());
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
}
