<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\EncryptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class RehashUserEmailHashes extends Command
{
    protected $signature = 'users:rehash-email-hashes
                                {--dry-run : Count rows whose email_hash would change}
                                {--chunk=100 : Rows processed per batch}';

    protected $description = 'Replace legacy unsalted user email_hash values with keyed HMAC values';

    public function handle(EncryptionService $encryption): int
    {
        if (! User::usesEncryptedStorage()) {
            $this->info('Encrypted user storage is not enabled on this connection.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $privateKey = $encryption->privateKey();
        $total = DB::table('users')->whereNotNull('email_hash')->count();
        $processed = 0;
        $changed = 0;

        try {
            DB::table('users')
                ->select('id')
                ->whereNotNull('email_hash')
                ->orderBy('id')
                ->chunkById($chunkSize, function ($rows) use ($encryption, $privateKey, $dryRun, &$processed, &$changed): void {
                    foreach ($rows as $row) {
                        $decrypted = DB::selectOne('SELECT id, email, email_hash FROM read_user(?, ?)', [
                            $row->id,
                            $privateKey,
                        ]);

                        if (! $decrypted) {
                            continue;
                        }

                        $expectedHash = $encryption->emailHash((string) $decrypted->email);
                        if ($expectedHash !== $decrypted->email_hash) {
                            $changed++;

                            if (! $dryRun) {
                                DB::table('users')
                                    ->where('id', $row->id)
                                    ->update(['email_hash' => $expectedHash]);
                            }
                        }

                        $processed++;
                    }
                });
        } catch (Throwable $e) {
            $this->error('Rehash failed: '.$this->sanitizeMessage($e->getMessage()));

            return self::FAILURE;
        }

        $this->table(
            ['Scanned', $dryRun ? 'Would update' : 'Updated'],
            [[$processed.' / '.$total, $changed]]
        );

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
