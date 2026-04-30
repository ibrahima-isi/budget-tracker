<?php

namespace App\Models;

use App\Services\AppCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'business_name',
        'business_email',
        'phone',
        'logo_path',
        'language',
        'default_currency',
    ];

    /** Always return the single settings row, creating it if absent. */
    public static function instance(): self
    {
        return Cache::remember(AppCache::SETTINGS_KEY, AppCache::SHARED_TTL, fn () => self::firstOrCreate([], [
            'business_name' => 'Mon Entreprise',
            'language' => 'fr',
            'default_currency' => 'XOF',
        ]));
    }

    public static function clearCache(): void
    {
        Cache::forget(AppCache::SETTINGS_KEY);
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::clearCache());
        static::deleted(fn () => self::clearCache());
    }
}
