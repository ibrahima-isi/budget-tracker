<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BlockMaliciousRequestsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/{path}', fn () => response('OK'))
            ->where('path', '.*');
    }

    public function test_malicious_paths_are_hidden(): void
    {
        foreach ([
            '/.env',
            '/.ssh/id_rsa',
            '/wp-admin/install.php',
            '/WP-CONTENT/uploads/shell',
            '/index.php',
            '/%2Eenv',
        ] as $path) {
            $this->get($path)->assertNotFound();
        }
    }

    public function test_query_strings_are_not_inspected(): void
    {
        $this->get('/login?redirect=.env')->assertOk();
    }

    public function test_similar_legitimate_path_names_are_not_blocked(): void
    {
        $this->get('/shellfish')->assertOk();
    }
}
