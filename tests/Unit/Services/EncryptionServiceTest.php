<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\EncryptionService;
use RuntimeException;
use Tests\TestCase;

class EncryptionServiceTest extends TestCase
{
    private function makeService(array $config = []): EncryptionService
    {
        // Override config values for isolated tests
        config([
            'encryption.public_key_path' => $config['path']  ?? '',
            'encryption.public_key'      => $config['pub']   ?? null,
            'encryption.private_key'     => $config['priv']  ?? null,
        ]);

        return new EncryptionService();
    }

    // ------------------------------------------------------------------
    // emailHash
    // ------------------------------------------------------------------

    public function test_email_hash_is_sha256_hex(): void
    {
        $svc  = $this->makeService();
        $hash = $svc->emailHash('Test@Example.COM');

        $this->assertSame(64, strlen($hash));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
    }

    public function test_email_hash_is_case_insensitive(): void
    {
        $svc = $this->makeService();

        $this->assertSame(
            $svc->emailHash('user@example.com'),
            $svc->emailHash('USER@EXAMPLE.COM'),
        );
    }

    public function test_email_hash_trims_whitespace(): void
    {
        $svc = $this->makeService();

        $this->assertSame(
            $svc->emailHash('user@example.com'),
            $svc->emailHash('  user@example.com  '),
        );
    }

    public function test_email_hash_is_deterministic(): void
    {
        $svc = $this->makeService();

        $this->assertSame(
            $svc->emailHash('hello@world.io'),
            $svc->emailHash('hello@world.io'),
        );
    }

    // ------------------------------------------------------------------
    // publicKey() — env fallback
    // ------------------------------------------------------------------

    public function test_public_key_loads_from_base64_env(): void
    {
        $armored = "-----BEGIN PGP PUBLIC KEY BLOCK-----\nfake\n-----END PGP PUBLIC KEY BLOCK-----\n";
        $b64     = base64_encode($armored);

        $svc = $this->makeService(['pub' => $b64]);

        $this->assertSame($armored, $svc->publicKey());
    }

    public function test_public_key_throws_when_missing(): void
    {
        $svc = $this->makeService(['path' => '', 'pub' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/APP_PUBLIC_KEY/');

        $svc->publicKey();
    }

    public function test_public_key_throws_on_invalid_base64(): void
    {
        $svc = $this->makeService(['pub' => '!!!not-base64!!!']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/not valid base64/');

        $svc->publicKey();
    }

    // ------------------------------------------------------------------
    // privateKey() — env only
    // ------------------------------------------------------------------

    public function test_private_key_loads_from_base64_env(): void
    {
        $armored = "-----BEGIN PGP PRIVATE KEY BLOCK-----\nfake\n-----END PGP PRIVATE KEY BLOCK-----\n";
        $b64     = base64_encode($armored);

        $svc = $this->makeService(['priv' => $b64]);

        $this->assertSame($armored, $svc->privateKey());
    }

    public function test_private_key_throws_when_missing(): void
    {
        $svc = $this->makeService(['priv' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/APP_PRIVATE_KEY/');

        $svc->privateKey();
    }

    // ------------------------------------------------------------------
    // __debugInfo — key material must not leak
    // ------------------------------------------------------------------

    public function test_debug_info_does_not_expose_key_material(): void
    {
        $armored = "-----BEGIN PGP PUBLIC KEY BLOCK-----\nfake\n-----END PGP PUBLIC KEY BLOCK-----\n";
        $svc     = $this->makeService(['pub' => base64_encode($armored)]);
        $svc->publicKey(); // warm cache

        $info = $svc->__debugInfo();

        $this->assertArrayNotHasKey('public_key',  $info);
        $this->assertArrayNotHasKey('private_key', $info);
        $this->assertTrue($info['public_key_loaded']);
        $this->assertFalse($info['private_key_loaded']);
    }

    // ------------------------------------------------------------------
    // flush
    // ------------------------------------------------------------------

    public function test_flush_clears_cached_keys(): void
    {
        $armored = "-----BEGIN PGP PUBLIC KEY BLOCK-----\nfake\n-----END PGP PUBLIC KEY BLOCK-----\n";
        $svc     = $this->makeService(['pub' => base64_encode($armored)]);
        $svc->publicKey();

        $this->assertTrue($svc->__debugInfo()['public_key_loaded']);

        $svc->flush();

        $this->assertFalse($svc->__debugInfo()['public_key_loaded']);
    }
}
