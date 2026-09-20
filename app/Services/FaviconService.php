<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Favicon Fetch Service
 * Strategy: HTML <link> tags → /favicon.ico → built-in placeholder
 * Cache: 24h on success, 1h on failure.
 */
class FaviconService
{
    private const CACHE_TTL_SUCCESS = 86400; // 24h
    private const CACHE_TTL_FAILURE = 3600;  // 1h
    private const TIMEOUT = 10;
    private const MAX_REDIRECTS = 3;
    private const MAX_FAVICON_SIZE = 524288; // 512KB

    /** @return array{favicon_url: string, source: string} */
    public function fetch(string $url): array
    {
        $parsed = parse_url($url);

        if (empty($parsed['scheme']) || empty($parsed['host'])) {
            return $this->defaultResult();
        }

        $scheme = $parsed['scheme'];
        $host   = $parsed['host'];
        $port   = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $origin = "{$scheme}://{$host}{$port}";
        $cacheKey = 'favicon:' . $host . $port;

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $client = $this->makeClient();

        $result = $this->fetchFromHtml($client, $url, $origin);
        if ($result !== null) {
            Cache::put($cacheKey, $result, self::CACHE_TTL_SUCCESS);
            return $result;
        }

        $result = $this->fetchDefaultIco($client, $origin);
        if ($result !== null) {
            Cache::put($cacheKey, $result, self::CACHE_TTL_SUCCESS);
            return $result;
        }

        $result = $this->defaultResult();
        Cache::put($cacheKey, $result, self::CACHE_TTL_FAILURE);
        return $result;
    }

    /** Clear cached favicon for the given URL's domain. */
    public function clearCache(string $url): void
    {
        $parsed = parse_url($url);
        if (empty($parsed['host'])) {
            return;
        }
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        Cache::forget('favicon:' . $parsed['host'] . $port);
    }

    private function makeClient(): Client
    {
        return new Client([
            RequestOptions::TIMEOUT         => self::TIMEOUT,
            RequestOptions::CONNECT_TIMEOUT => 5,
            RequestOptions::ALLOW_REDIRECTS => [
                'max'     => self::MAX_REDIRECTS,
                'strict'  => true,
            ],
            RequestOptions::HEADERS => [
                'User-Agent' => 'Aine-Favicon-Bot/1.0',
                'Accept'     => 'text/html,image/*,*/*;q=0.8',
            ],
            RequestOptions::HTTP_ERRORS => false,
        ]);
    }

    /** @return array{favicon_url: string, source: string}|null */
    private function fetchFromHtml(Client $client, string $url, string $origin): ?array
    {
        try {
            $response = $client->get($url);

            if ($response->getStatusCode() >= 400) {
                return null;
            }

            $body = $response->getBody()->getContents();

            $head = $this->extractHead($body);
            if ($head === null) {
                return null;
            }

            $candidates = [];

            // Match all <link> tags where rel is an icon type
            if (preg_match_all(
                '/<link\s[^>]*rel=["\']((?:shortcut\s+)?icon|apple-touch-icon(?:-precomposed)?)["\'][^>]*>/i',
                $head,
                $matches,
                PREG_SET_ORDER
            )) {
                foreach ($matches as $match) {
                    $tag = $match[0];
                    $rel = strtolower($match[1]);

                    if (!preg_match('/href=["\']([^"\']+)["\']/i', $tag, $hrefMatch)) {
                        continue;
                    }
                    $href = $hrefMatch[1];

                    $size = 0;
                    if (preg_match('/sizes=["\'](\d+)x\d+/i', $tag, $sizeMatch)) {
                        $size = (int) $sizeMatch[1];
                    }

                    $candidates[] = [
                        'href' => $href,
                        'size' => $size,
                        'rel'  => $rel,
                    ];
                }
            }

            if (empty($candidates)) {
                return null;
            }

            usort($candidates, function ($a, $b) {
                $aIsApple = str_contains($a['rel'], 'apple') ? 1 : 0;
                $bIsApple = str_contains($b['rel'], 'apple') ? 1 : 0;
                if ($aIsApple !== $bIsApple) return $bIsApple - $aIsApple;

                if ($a['size'] !== $b['size']) return $b['size'] - $a['size'];

                return 0;
            });

            foreach ($candidates as $candidate) {
                $faviconUrl = $this->resolveUrl($candidate['href'], $origin);
                if ($faviconUrl === null) {
                    continue;
                }

                if ($this->verifyFavicon($client, $faviconUrl)) {
                    return [
                        'favicon_url' => $faviconUrl,
                        'source'      => 'html',
                    ];
                }
            }

            return null;
        } catch (\Throwable $e) {
            Log::debug('Favicon HTML fetch failed', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /** @return array{favicon_url: string, source: string}|null */
    private function fetchDefaultIco(Client $client, string $origin): ?array
    {
        $icoUrl = rtrim($origin, '/') . '/favicon.ico';

        try {
            $response = $client->head($icoUrl);

            if ($response->getStatusCode() >= 400) {
                return null;
            }

            $contentType = $response->getHeaderLine('Content-Type');
            if ($contentType && !str_starts_with($contentType, 'image/') && !str_contains($contentType, 'octet-stream')) {
                return null;
            }

            $contentLength = $response->getHeaderLine('Content-Length');
            if ($contentLength && (int) $contentLength > self::MAX_FAVICON_SIZE) {
                return null;
            }

            return [
                'favicon_url' => $icoUrl,
                'source'      => 'default-ico',
            ];
        } catch (\Throwable $e) {
            Log::debug('Favicon /favicon.ico fetch failed', [
                'origin' => $origin,
                'error'  => $e->getMessage(),
            ]);
            return null;
        }
    }

    /** Verify the favicon URL is accessible and returns a valid image. */
    private function verifyFavicon(Client $client, string $faviconUrl): bool
    {
        try {
            $response = $client->head($faviconUrl);

            if ($response->getStatusCode() >= 400) {
                return false;
            }

            $contentType = $response->getHeaderLine('Content-Type');
            if ($contentType && !str_starts_with($contentType, 'image/')
                && !str_contains($contentType, 'octet-stream')
                && !str_contains($contentType, 'svg+xml')) {
                return false;
            }

            $contentLength = $response->getHeaderLine('Content-Length');
            if ($contentLength && (int) $contentLength > self::MAX_FAVICON_SIZE) {
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Extract the <head> section from HTML. */
    private function extractHead(string $html): ?string
    {
        if (preg_match('/<head[^>]*>(.*?)<\/head>/is', $html, $match)) {
            return $match[1];
        }

        if (strlen($html) > 0) {
            return substr($html, 0, 8192);
        }

        return null;
    }

    /** Resolve a relative URL to an absolute URL. */
    private function resolveUrl(string $href, string $origin): ?string
    {
        $href = trim($href);

        if ($href === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $href)) {
            return $href;
        }

        if (str_starts_with($href, '//')) {
            $scheme = parse_url($origin, PHP_URL_SCHEME);
            return $scheme . ':' . $href;
        }

        if (str_starts_with($href, '/')) {
            return rtrim($origin, '/') . $href;
        }

        return rtrim($origin, '/') . '/' . $href;
    }

    /** @return array{favicon_url: string, source: string} */
    private function defaultResult(): array
    {
        return [
            'favicon_url' => url('/images/favicon.svg'),
            'source'      => 'default',
        ];
    }
}
