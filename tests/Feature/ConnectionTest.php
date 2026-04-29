<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConnectionTest extends TestCase
{
    public function test_connection_driver(): void
    {
        $this->assertNotEmpty(DB::connection()->getDriverName());
    }
}
