<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Spatie\Permission\Exceptions\UnauthorizedException;

$bootstrapEnv = dirname(__DIR__).'/.env';
if (! file_exists(dirname(__DIR__).'/storage/installed')) {
    if (! file_exists($bootstrapEnv) && file_exists(dirname(__DIR__).'/.env.example')) {
        copy(dirname(__DIR__).'/.env.example', $bootstrapEnv);
    }
    if (file_exists($bootstrapEnv)) {
        $bootstrapEnvContent = (string) file_get_contents($bootstrapEnv);
        if (! preg_match('/^APP_KEY=.+/m', $bootstrapEnvContent)) {
            $bootstrapKey = 'base64:'.base64_encode(random_bytes(32));
            file_put_contents(
                $bootstrapEnv,
                preg_replace('/^APP_KEY=.*$/m', 'APP_KEY='.$bootstrapKey, $bootstrapEnvContent)
            );
        }
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        then: function () {
            // All web routes are registered in RouteServiceProvider::boot() in a
            // fixed order (admin -> frontend -> auth) so the configurable admin
            // routes match before the frontend catch-all.
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(\App\Http\Middleware\ConvertDotNotationQueryParams::class);
        $middleware->prepend(\App\Http\Middleware\RedirectIfNotInstalled::class);
        $middleware->prepend(\App\Http\Middleware\DynamicCors::class);
        $middleware->prepend(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->prepend(\App\Http\Middleware\PreventRequestsDuringMaintenanceExceptAdmin::class);

        $middleware->appendToGroup('api', [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'throttle:api',
        ]);

        $middleware->replaceInGroup(
            'web',
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
            \App\Http\Middleware\VerifyCsrfToken::class
        );

        $middleware->alias([
            'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
            'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
            'can' => \Illuminate\Auth\Middleware\Authorize::class,
            'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
            'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'backend.user' => \App\Http\Middleware\EnsureBackendUser::class,
            'verify.domain.whitelist' => \App\Http\Middleware\VerifyDomainWhitelist::class,
            'validate.project.access' => \App\Http\Middleware\ValidateProjectAccess::class,
            'dynamic.cors' => \App\Http\Middleware\DynamicCors::class,
            'project.readonly' => \App\Http\Middleware\BlockInactiveProjectWrites::class,
        ]);

        $middleware->redirectUsersTo(\App\Support\AdminPath::prefix());

        $middleware->redirectGuestsTo(function ($request) {
            if (\App\Support\AdminPath::isAdminRequest($request)) {
                return route("admin.login");
            }
            return route("login");
        });

        $middleware->validateCsrfTokens(except: [
            'forms/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (!$request->is('api/*') && ! \App\Support\AdminPath::isAdminRequest($request)) {
                return null;
            }

            if ($exception instanceof ValidationException) {
                $firstError = collect($exception->errors())->flatten()->first();

                return response()->json([
                    'success' => false, 'code' => 422,
                    'message' => $firstError ?: 'Validation failed',
                    'data' => $exception->errors(),
                ], 422);
            }

            if ($exception instanceof ModelNotFoundException) {
                return response()->json([
                    'success' => false, 'code' => 404,
                    'message' => 'Resource not found', 'data' => null,
                ], 404);
            }

            if ($exception instanceof NotFoundHttpException) {
                return response()->json([
                    'success' => false, 'code' => 404,
                    'message' => 'Not found', 'data' => null,
                ], 404);
            }

            if ($exception instanceof UnauthorizedHttpException) {
                return response()->json([
                    'success' => false, 'code' => 401,
                    'message' => 'Unauthorized', 'data' => null,
                ], 401);
            }

            if ($exception instanceof AuthenticationException) {
                if (! $request->expectsJson() && ! \App\Support\AdminPath::isAdminApiRequest($request)) {
                    return null;
                }

                return response()->json([
                    'success' => false, 'code' => 401,
                    'message' => 'Unauthenticated', 'data' => null,
                ], 401);
            }

            if ($exception instanceof AccessDeniedHttpException) {
                return response()->json([
                    'success' => false, 'code' => 403,
                    'message' => 'Forbidden', 'data' => null,
                ], 403);
            }

            if ($exception instanceof UnauthorizedException) {
                return response()->json([
                    'success' => false, 'code' => 403,
                    'message' => 'Forbidden', 'data' => null,
                ], 403);
            }

            if ($exception instanceof HttpException && $exception->getStatusCode() === 419) {
                return response()->json([
                    'success' => false, 'code' => 419,
                    'message' => 'Session expired', 'data' => null,
                ], 419);
            }

            return response()->json([
                'success' => false, 'code' => 500,
                'message' => 'Internal server error', 'data' => null,
            ], 500);
        });
    })
    ->create();
