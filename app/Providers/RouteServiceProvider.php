<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use App\Support\AdminPath;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The "home" route a backend user is redirected to after login.
     *
     * Resolved dynamically so it follows the configurable admin path.
     */
    public static function home(): string
    {
        return AdminPath::prefix();
    }

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        Route::middleware('web')
            ->group(base_path('routes/admin.php'));

        Route::middleware('web')
            ->group(base_path('routes/frontend.php'));

        Route::middleware('web')
            ->group(base_path('routes/auth.php'));
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('api-write', function (Request $request) {
            return Limit::perMinute(30)->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('api-login', function (Request $request) {
            return Limit::perMinute(10)->by('api-login:'.$request->ip());
        });

        RateLimiter::for('api-search', function (Request $request) {
            return optional($request->user())->id
                ? Limit::perMinute(60)->by('search:user:'.$request->user()->id)
                : Limit::perMinute(20)->by('search:ip:'.$request->ip());
        });

        RateLimiter::for('form-submit', function (Request $request) {
            return Limit::perMinutes(10, 20)->by('form-submit:'.$request->ip());
        });

        RateLimiter::for('form-upload', function (Request $request) {
            return Limit::perMinutes(10, 30)->by('form-upload:'.$request->ip());
        });

        RateLimiter::for('preview', function (Request $request) {
            return Limit::perMinute(60)->by('preview:'.$request->ip());
        });
    }
}
