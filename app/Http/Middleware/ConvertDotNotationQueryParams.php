<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Convert dot-notation query-string keys into nested arrays.
 */
class ConvertDotNotationQueryParams
{
    public function handle(Request $request, Closure $next)
    {
        // Query parameters only matter for GET/HEAD requests.
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $queryString = $request->server('QUERY_STRING', '');
        if ($queryString === '') {
            return $next($request);
        }

        $nested = $this->parseDotNotation($queryString);
        if ($nested === []) {
            return $next($request);
        }

        $this->removeMangledKeys($request, $nested);
        $request->merge($nested);

        return $next($request);
    }

    /**
     * Parse a raw query string and return only the dot-notation
     * parameters rebuilt as nested arrays.
     *
     * "filters.locale=zh&filters.category.slug=tech&sort=created_at:desc"
     *   => ['filters' => ['locale' => 'zh', 'category' => ['slug' => 'tech']]]
     */
    private function parseDotNotation(string $queryString): array
    {
        $result = [];

        foreach (explode('&', $queryString) as $pair) {
            if ($pair === '') {
                continue;
            }

            $parts = explode('=', $pair, 2);
            $key = rawurldecode($parts[0]);
            $value = isset($parts[1]) ? rawurldecode($parts[1]) : '';

            if (str_contains($key, '[') || str_contains($key, ']')) {
                continue;
            }

            if (! str_contains($key, '.')) {
                continue;
            }

            $segments = explode('.', $key);
            $current = &$result;
            foreach ($segments as $segment) {
                if ($segment === '') {
                    continue 2;
                }
                if (! isset($current[$segment]) || ! is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
            $current = $value;
        }

        return $result;
    }

    /**
     * Remove the underscore-mangled flat keys PHP created for every
     * dot-notation parameter we just rebuilt.
     */
    private function removeMangledKeys(Request $request, array $nested): void
    {
        $flatKeys = $this->flattenDotKeys($nested);
        foreach ($flatKeys as $dotKey) {
            $mangled = str_replace('.', '_', $dotKey);
            if ($request->query->has($mangled)) {
                $request->query->remove($mangled);
            }
        }
    }

    /**
     * Flatten a nested array into a list of dot-notation keys.
     *
     * ['filters' => ['locale' => 'zh', 'category' => ['slug' => 'tech']]]
     *   => ['filters.locale', 'filters.category.slug']
     */
    private function flattenDotKeys(array $array, string $prefix = ''): array
    {
        $keys = [];
        foreach ($array as $key => $value) {
            $fullKey = $prefix === '' ? $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $keys = array_merge($keys, $this->flattenDotKeys($value, $fullKey));
            } else {
                $keys[] = $fullKey;
            }
        }
        return $keys;
    }
}
