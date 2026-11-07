<?php

namespace OpenJourney\LaravelJourneyBuilder\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/** No package-owned login, password, cookies or redirects. */
final class DashboardAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $mode = config('journey_builder.dashboard_access', 'gate');

        if ($mode === 'gate' && auth()->check() && Gate::allows(config('journey_builder.dashboard_gate', 'manage-journeys'))) {
            return $next($request);
        }

        if ($mode === 'auth' && auth()->check()) {
            return $next($request);
        }
        // Local development ONLY. Reverse proxies MUST NOT expose local IP as loopback.
        if ($mode === 'local' && app()->environment('local') &&
            in_array($request->ip(), ['127.0.0.1', '::1'], true)) {
            return $next($request);
        }

        abort(403, 'Journey Builder dashboard requires host application authentication.');
    }
}
