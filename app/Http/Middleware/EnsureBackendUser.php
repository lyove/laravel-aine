<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Support\AdminPath;

/**
 * Gate the admin area to backend users only.
 *
 * Only users holding a backend role (super_admin or editor) may access the
 * admin SPA and its admin-api endpoints. Registered frontend users (the
 * `user` role) are locked out even though they share the same web session.
 */
class EnsureBackendUser
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && ($user->hasRole('super_admin') || $user->hasRole('editor'))) {
            return $next($request);
        }

        if ($request->expectsJson() || AdminPath::isAdminApiRequest($request)) {
            return response()->json([
                'success' => false,
                'code' => 403,
                'message' => 'Forbidden',
                'data' => null,
            ], 403);
        }

        return redirect('/');
    }
}
