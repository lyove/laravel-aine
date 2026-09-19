<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminLoginController extends Controller
{
    /**
     * Display the admin login view.
     */
    public function create()
    {
        return view('auth.admin-login');
    }

    /**
     * Handle an incoming admin authentication request.
     * Only users with backend roles (super_admin / admin / editor) may log in here.
     */
    public function store(LoginRequest $request)
    {
        $request->authenticate();

        // Forward to 2FA challenge if enabled.
        if (session()->has('login.two_factor_user_id')) {
            return redirect()->route('two-factor.challenge');
        }

        $user = $request->user();

        // Reject non-backend users from the admin login.
        if (! $user || ! $this->isBackendUser($user)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('These credentials do not grant access to the admin area.')]);
        }

        $request->session()->regenerate();

        return redirect()->intended(RouteServiceProvider::home());
    }

    /**
     * Log out and return to the admin login page.
     */
    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    /**
     * Determine whether the user belongs to the backend.
     */
    protected function isBackendUser($user): bool
    {
        return $user->hasRole('super_admin')
            || $user->hasRole('admin')
            || $user->hasRole('editor');
    }
}
