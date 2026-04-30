<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class AppCache
{
    public const DASHBOARD_TTL = 60;

    public const SHARED_TTL = 3600;

    public const SETTINGS_KEY = 'shared:settings';

    public const CURRENCIES_ACTIVE_KEY = 'shared:currencies:active';

    public const CURRENCIES_ORDERED_KEY = 'shared:currencies:ordered';

    public static function financeKey(int $userId, string $name, array $parts = []): string
    {
        return implode(':', [
            'finance',
            $userId,
            self::financeVersion($userId),
            $name,
            sha1(json_encode($parts, JSON_THROW_ON_ERROR)),
        ]);
    }

    public static function bumpFinanceVersion(?int $userId): void
    {
        if (! $userId) {
            return;
        }

        Cache::forever(self::financeVersionKey($userId), now()->getTimestamp());
    }

    public static function financeVersion(int $userId): int
    {
        return (int) Cache::rememberForever(
            self::financeVersionKey($userId),
            fn () => now()->getTimestamp()
        );
    }

    public static function clearShared(): void
    {
        Cache::forget(self::SETTINGS_KEY);
        Cache::forget(self::CURRENCIES_ACTIVE_KEY);
        Cache::forget(self::CURRENCIES_ORDERED_KEY);
    }

    private static function financeVersionKey(int $userId): string
    {
        return "finance:{$userId}:version";
    }
}
