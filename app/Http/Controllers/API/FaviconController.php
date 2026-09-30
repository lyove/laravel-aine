<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\FaviconService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Favicon Fetch API
 * GET /api/getFavicon?url=https://example.com&refresh=1
 */
class FaviconController extends Controller
{
    public function __construct(
        private readonly FaviconService $faviconService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $url = $request->query('url');

        if (empty($url)) {
            return $this->error(__('Parameter "url" is required'), 400);
        }

        // URL validation
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->error(__('Invalid URL format'), 422);
        }

        $parsed = parse_url($url);
        if (empty($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'], true)) {
            return $this->error(__('URL scheme must be http or https'), 422);
        }

        if (empty($parsed['host'])) {
            return $this->error(__('URL must contain a valid host'), 422);
        }

        if ($request->boolean('refresh')) {
            $this->faviconService->clearCache($url);
        }

        $result = $this->faviconService->fetch($url);

        return $this->success($result);
    }
}
