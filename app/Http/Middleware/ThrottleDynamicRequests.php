<?php

namespace App\Http\Middleware;

use App\Support\NetworkRateLimitKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleDynamicRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.dynamic_requests.enabled', true) || $this->isExcluded($request)) {
            return $next($request);
        }

        $ip = $request->ip() ?: 'unknown';
        $limits = [
            [
                'counter' => "dynamic:ip:{$ip}",
                'block' => "dynamic:block:ip:{$ip}",
                'max' => (int) config('security.dynamic_requests.ip_attempts', 120),
            ],
            [
                'counter' => 'dynamic:network:'.NetworkRateLimitKey::fromIp($ip),
                'block' => 'dynamic:block:network:'.NetworkRateLimitKey::fromIp($ip),
                'max' => (int) config('security.dynamic_requests.network_attempts', 600),
            ],
        ];

        $blockSeconds = (int) config('security.dynamic_requests.block_seconds', 900);

        foreach ($limits as $limit) {
            if (RateLimiter::tooManyAttempts($limit['block'], 1)) {
                return $this->tooManyRequests(RateLimiter::availableIn($limit['block']), $limit['max']);
            }
        }

        foreach ($limits as $limit) {
            if (RateLimiter::tooManyAttempts($limit['counter'], $limit['max'])) {
                RateLimiter::hit($limit['block'], $blockSeconds);

                return $this->tooManyRequests(RateLimiter::availableIn($limit['block']), $limit['max']);
            }
        }

        foreach ($limits as $limit) {
            RateLimiter::hit($limit['counter'], (int) config('security.dynamic_requests.window_seconds', 60));
        }

        return $next($request);
    }

    private function isExcluded(Request $request): bool
    {
        foreach (config('security.dynamic_requests.excluded_paths', []) as $path) {
            $path = trim((string) $path, '/');

            if ($path !== '' && $request->is($path)) {
                return true;
            }
        }

        return false;
    }

    private function tooManyRequests(int $retryAfter, int $limit): Response
    {
        $retryAfter = max(1, $retryAfter);

        return response('Too Many Requests.', 429, [
            'Retry-After' => (string) $retryAfter,
            'X-RateLimit-Limit' => (string) $limit,
            'X-RateLimit-Remaining' => '0',
        ]);
    }
}
