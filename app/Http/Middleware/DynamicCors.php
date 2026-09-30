<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DynamicCors
{
    /**
     * Handle an incoming request with dynamic CORS headers based on domain_whitelist.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (!$this->isApiRoute($request)) {
            return $next($request);
        }

        $origin = $request->header('origin');

        if ($origin && $this->isOriginAllowed($this->hostFromOrigin($origin))) {
            if ($request->isMethod('OPTIONS')) {
                return response('', 204)->withHeaders($this->corsHeaders($origin));
            }

            $response = $next($request);
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Max-Age', '86400');

            return $response;
        }

        return $next($request);
    }

    /**
     * Check if the request is for API routes
     */
    protected function isApiRoute(Request $request): bool
    {
        return $request->is('api/*');
    }

    /**
     * Extract host from an Origin header value.
     */
    protected function hostFromOrigin(string $origin): ?string
    {
        $parsedUrl = parse_url($origin);

        return $parsedUrl['host'] ?? null;
    }

    /**
     * Check if the origin is in any project's domain_whitelist.
     */
    protected function isOriginAllowed(?string $host): bool
    {
        if (!$host) {
            return false;
        }

        return (bool) Cache::remember(
            self::cacheKey($host),
            300,
            function () use ($host) {
                return Project::all()->contains(function (Project $project) use ($host) {
                    $whitelist = $project->domain_whitelist;
                    if (empty($whitelist) || ! is_array($whitelist)) {
                        return false;
                    }

                    foreach ($whitelist as $entry) {
                        if (! is_string($entry) || $entry === '') {
                            continue;
                        }

                        $entryHost = strtolower((string) parse_url($entry, PHP_URL_HOST));
                        if ($entryHost !== '' && $entryHost === strtolower($host)) {
                            return true;
                        }

                        if (strtolower($entry) === strtolower($host)) {
                            return true;
                        }
                    }

                    return false;
                });
            }
        );
    }

    /**
     * Cache key for a host's whitelist decision.
     */
    public static function cacheKey(string $host): string
    {
        return 'dynamic_cors:origin:'.strtolower($host);
    }

    /**
     * Drop the cached decision for a host (call when its whitelist changes).
     */
    public static function forgetCacheKey(string $host): void
    {
        Cache::forget(self::cacheKey($host));
    }

    /**
     * Build CORS response headers for a whitelisted origin.
     */
    protected function corsHeaders(string $origin): array
    {
        return [
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age' => '86400',
        ];
    }
}
