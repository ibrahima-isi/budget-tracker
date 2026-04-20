<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces subdomain separation:
 *  - admin.gui-connect.com  → only /settings* routes (redirects everything else to client)
 *  - budget.gui-connect.com → main app (redirects /settings* to admin subdomain)
 *
 * Skipped when ADMIN_URL is not configured (local / test environments).
 */
class SubdomainRouter
{
    public function handle(Request $request, Closure $next): Response
    {
        $adminUrl  = config('app.admin_url');
        $clientUrl = config('app.url');

        if (! $adminUrl || ! $clientUrl) {
            return $next($request);
        }

        $adminHost  = parse_url($adminUrl,  PHP_URL_HOST);
        $clientHost = parse_url($clientUrl, PHP_URL_HOST);
        $host       = $request->getHost();
        $path       = $request->getPathInfo();

        // On admin subdomain: only settings routes are allowed
        if ($host === $adminHost && ! str_starts_with($path, '/settings')) {
            return redirect($clientUrl . $request->getRequestUri());
        }

        // On client subdomain: send /settings* to admin subdomain
        if ($host === $clientHost && str_starts_with($path, '/settings')) {
            return redirect($adminUrl . $request->getRequestUri());
        }

        return $next($request);
    }
}
