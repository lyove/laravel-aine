<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;
use App\Support\AdminPath;

/**
 * Apply baseline security headers to every response.
 *
 * The admin area is additionally protected against clickjacking
 * (X-Frame-Options: SAMEORIGIN). The public site intentionally skips the
 * frame header because embedded forms are a core feature: third-party sites
 * load our frontend inside an <iframe>, which any frame restriction would
 * break.
 */
class SecurityHeaders
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=()'
        );

        // Production-only: enforce HTTPS via HSTS.
        if (App::isProduction()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        // Content-Security-Policy: assembled from config('app.csp') so each
        // deployment can customize allowed sources without editing middleware.
        $csp = config('app.csp', []);
        if ($csp) {
            $policy = '';
            foreach ($csp as $directive => $sources) {
                $policy .= $directive . ' ' . implode(' ', $sources) . '; ';
            }
            $response->headers->set('Content-Security-Policy', rtrim($policy));
        }

        if (AdminPath::isAdminRequest($request)) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        return $response;
    }
}
