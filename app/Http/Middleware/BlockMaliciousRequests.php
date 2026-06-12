<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockMaliciousRequests
{
    private const BLOCKED_SEGMENTS = [
        '.env',
        '.ssh',
        'id_rsa',
        'wp-admin',
        'wp-content',
        'shell',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $path = strtolower(rawurldecode($request->getPathInfo()));
        $segments = preg_split('/[\/\\\\]+/', $path, flags: PREG_SPLIT_NO_EMPTY);

        foreach ($segments as $segment) {
            if (in_array($segment, self::BLOCKED_SEGMENTS, true) || str_ends_with($segment, '.php')) {
                abort(404);
            }
        }

        return $next($request);
    }
}
