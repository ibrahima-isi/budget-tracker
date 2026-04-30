<?php

namespace Tests\Unit\Services;

use App\Services\AppCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AppCacheTest extends TestCase
{
    public function test_bumping_finance_version_changes_finance_cache_keys(): void
    {
        Cache::flush();

        $parts = ['month' => 4, 'year' => 2026, 'currency' => 'XOF'];
        $firstKey = AppCache::financeKey(1, 'dashboard:monthly', $parts);

        AppCache::bumpFinanceVersion(1);
        $secondKey = AppCache::financeKey(1, 'dashboard:monthly', $parts);

        AppCache::bumpFinanceVersion(1);
        $thirdKey = AppCache::financeKey(1, 'dashboard:monthly', $parts);

        $this->assertNotSame($firstKey, $secondKey);
        $this->assertNotSame($secondKey, $thirdKey);
    }
}
