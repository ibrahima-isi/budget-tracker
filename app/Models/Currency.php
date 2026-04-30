<?php

namespace App\Models;

use App\Services\AppCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class Currency extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'symbol', 'is_default', 'is_active'];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public static function activeOptions(): Collection
    {
        return Cache::remember(AppCache::CURRENCIES_ACTIVE_KEY, AppCache::SHARED_TTL, fn () => self::where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('code')
            ->get(['code', 'name', 'symbol']));
    }

    public static function orderedOptions(): Collection
    {
        return Cache::remember(AppCache::CURRENCIES_ORDERED_KEY, AppCache::SHARED_TTL, fn () => self::orderBy('is_default', 'desc')
            ->orderBy('code')
            ->get());
    }

    public static function clearCache(): void
    {
        Cache::forget(AppCache::CURRENCIES_ACTIVE_KEY);
        Cache::forget(AppCache::CURRENCIES_ORDERED_KEY);
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::clearCache());
        static::deleted(fn () => self::clearCache());
    }
}
