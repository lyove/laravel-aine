<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\Request;
use Throwable;

/**
 * Resolves the current admin entry path.
 *
 * Page routes live at "/{slug}", API routes at "/{slug}-api".
 */
class AdminPath
{
    /**
     * Default slug used when nothing is configured yet.
     */
    public const DEFAULT = 'admin';

    /**
     * Slugs that may never be used as a custom admin path, because they
     * collide with public routes, reserved paths, or would break the app.
     */
    public const RESERVED = [
        '',
        'admin',
        'api',
        'admin-api',
        'login',
        'register',
        'forgot-password',
        'reset-password',
        'password',
        'install',
        'installer',
        'storage',
        'public',
        'assets',
        'css',
        'js',
        'images',
        'img',
        'favicon.ico',
        'robots.txt',
        'sitemap.xml',
        'feed',
    ];

    /**
     * Per-request cache of the resolved slug.
     */
    protected static ?string $slug = null;

    /**
     * The admin path slug without a leading slash (e.g. "admin" or "mx9k2").
     */
    public static function slug(): string
    {
        if (self::$slug !== null) {
            return self::$slug;
        }

        if (! file_exists(base_path('storage/installed'))) {
            return self::$slug = self::DEFAULT;
        }

        try {
            $value = Setting::query()->value('admin_path');
        } catch (Throwable $e) {
            // DB not ready yet (route registration phase): fall back to default
            // without caching, so the next call after boot re-resolves it.
            return self::DEFAULT;
        }

        $slug = is_string($value) ? trim($value, '/ ') : '';

        if ($slug === '' || ! self::isValidSlug($slug)) {
            $slug = self::DEFAULT;
        }

        return self::$slug = $slug;
    }

    /**
     * Page route prefix with leading slash (e.g. "/admin" or "/mx9k2").
     */
    public static function prefix(): string
    {
        return '/'.self::slug();
    }

    /**
     * API route prefix with leading slash (e.g. "/admin-api" or "/mx9k2-api").
     */
    public static function apiPrefix(): string
    {
        return '/'.self::apiSlug();
    }

    /**
     * API slug without leading slash (e.g. "admin-api" or "mx9k2-api").
     */
    public static function apiSlug(): string
    {
        return self::slug().'-api';
    }

    /**
     * Whether the given request targets the admin page area or its API.
     */
    public static function isAdminRequest(Request $request): bool
    {
        $path = trim($request->path(), '/');

        $page = self::slug();
        $api = self::apiSlug();

        if ($path === $page || str_starts_with($path, $page.'/')) {
            return true;
        }

        if ($path === $api || str_starts_with($path, $api.'/')) {
            return true;
        }

        return false;
    }

    /**
     * Whether the given request targets the admin API prefix only.
     */
    public static function isAdminApiRequest(Request $request): bool
    {
        $path = trim($request->path(), '/');
        $api = self::apiSlug();

        return $path === $api || str_starts_with($path, $api.'/');
    }

    /**
     * Validate a candidate slug. Used both server-side on save and to guard
     * the resolved value above.
     */
    public static function isValidSlug(string $slug): bool
    {
        if ($slug === self::DEFAULT) {
            return true;
        }

        if (! preg_match('/^[a-z][a-z0-9\-]{1,59}$/', $slug)) {
            return false;
        }

        if (in_array(strtolower($slug), self::RESERVED, true)) {
            return false;
        }

        return true;
    }

    /**
     * Reset the per-request cache. Mainly useful in tests.
     */
    public static function flush(): void
    {
        self::$slug = null;
    }
}
