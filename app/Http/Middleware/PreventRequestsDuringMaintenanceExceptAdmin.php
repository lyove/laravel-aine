<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Support\AdminPath;

/**
 * Maintenance-mode middleware that keeps the admin panel accessible.
 */
class PreventRequestsDuringMaintenanceExceptAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (AdminPath::isAdminRequest($request)) {
            return $next($request);
        }

        $downFile = storage_path('framework/down');

        if (file_exists($downFile)) {
            $data = json_decode(file_get_contents($downFile), true) ?: [];

            return response()->view('errors::503', [
                'message' => $data['message'] ?? 'Service Unavailable',
                'retry'   => $data['retry'] ?? null,
            ], 503);
        }

        return $next($request);
    }
}
