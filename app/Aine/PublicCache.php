<?php

namespace App\Aine;

use Illuminate\Support\Facades\Cache;

/**
 * Helpers for invalidating the public API response cache.
 *
 * The public API caches read responses under a per-project cache key that
 * embeds a "content version". Every content write (create / update / trash /
 * delete / publish / unpublish / restore) must bump that version so cached
 * responses are regenerated on the next request.
 *
 * Invalidation is driven by the content events (see BumpPublicCache
 * listener), so every write path — the admin panel as well as the public
 * API — invalidates consistently without each controller having to remember
 * to do it.
 */
class PublicCache
{
    /**
     * TTL of the per-project version key (7 days)
     */
    const CACHE_VERSION_TTL = 7 * 86400;

    /**
     * Current cache version for a project
     *
     * @param int $projectId
     * @param string|null $collection  Collection slug for a collection-scoped version.
     * @return int
     */
    public static function version(int $projectId, ?string $collection = null): int
    {
        return (int) self::safeGet(self::versionKey($projectId, $collection), 0);
    }

    /**
     * Invalidate cached public responses of a project by incrementing a
     * cache version. Called after every content write.
     *
     * @param int $projectId
     * @param string|null $collection  Collection slug to scope the bump to.
     *                                 Null bumps the project-wide version and
     *                                 therefore invalidates every cached
     *                                 public response of the project.
     * @return void
     */
    public static function bump(int $projectId, ?string $collection = null): void
    {
        $key = self::versionKey($projectId, $collection);
        $version = (int) self::safeGet($key, 0);
        self::safePut($key, $version + 1, self::CACHE_VERSION_TTL);
    }

    /**
     * Cache key for a project (and optional collection) version.
     *
     * @param int $projectId
     * @param string|null $collection
     * @return string
     */
    private static function versionKey(int $projectId, ?string $collection): string
    {
        $key = 'public_content_version:'.$projectId;

        if ($collection !== null && $collection !== '') {
            $key .= ':'.$collection;
        }

        return $key;
    }

    /**
     * Cache read with graceful degradation. A cache failure (e.g. Redis not
     * running, file cache not writable) must never break the request.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private static function safeGet($key, $default = null)
    {
        try {
            return Cache::get($key, $default);
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * Cache write with graceful degradation. Failures are swallowed so a
     * broken cache driver can never break the request.
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @return void
     */
    private static function safePut($key, $value, $ttl)
    {
        try {
            Cache::put($key, $value, $ttl);
        } catch (\Throwable $e) {
            // Cache is an optimisation only — never fail the request over it.
        }
    }
}
