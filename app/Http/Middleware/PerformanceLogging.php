<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PerformanceLogging
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! filter_var(env('PERFORMANCE_LOGGING', false), FILTER_VALIDATE_BOOLEAN)) {
            return $next($request);
        }

        $queryCount = 0;
        $queryTimeMs = 0.0;

        DB::listen(function (QueryExecuted $query) use (&$queryCount, &$queryTimeMs): void {
            $queryCount++;
            $queryTimeMs += $query->time;
        });

        $startedAt = microtime(true);
        $response = $next($request);
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::info('performance.request', [
            'method' => $request->method(),
            'path' => $request->path(),
            'route' => $request->route()?->getName(),
            'status' => $response->getStatusCode(),
            'duration_ms' => round($durationMs, 2),
            'sql_count' => $queryCount,
            'sql_ms' => round($queryTimeMs, 2),
        ]);

        return $response;
    }
}
