<?php

namespace App\Http\Requests\Auth;

use App\Aine\AuditLogger;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /** Account-level lockout: max failed attempts per account (across all IPs). */
    private const ACCOUNT_MAX_ATTEMPTS = 10;

    /** Account-level lockout decay in seconds. */
    private const ACCOUNT_DECAY_SECONDS = 600; // 10 minutes

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * If the user has two-factor authentication enabled, the session is only
     * "primed" (pending user id stored) and the caller must redirect to the
     * two-factor challenge screen instead of logging the user in.
     *
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate()
    {
        $this->ensureIsNotRateLimited();
        $this->ensureAccountNotLocked();

        $credentials = $this->only('email', 'password');

        $user = User::where('email', $this->string('email'))->first();

        if (! $user || ! Auth::validate($credentials)) {
            RateLimiter::hit($this->throttleKey());
            RateLimiter::hit($this->accountLockoutKey(), self::ACCOUNT_DECAY_SECONDS);

            AuditLogger::log('failed_login', 'user', $user?->id, $this->string('email'), [
                'ip' => $this->ip(),
                'account_failures' => RateLimiter::attempts($this->accountLockoutKey()),
            ]);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->accountLockoutKey());

        // Two-factor enabled: hold off authentication until the code is verified.
        if ($user->twoFactorEnabled()) {
            session()->put('login.two_factor_user_id', $user->getKey());

            return;
        }

        Auth::login($user, $this->filled('remember'));
    }

    /**
     * Ensure the login request is not rate limited per {email, IP}.
     *
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited()
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Ensure the account itself is not locked due to cross-IP failed attempts.
     *
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureAccountNotLocked()
    {
        if (! RateLimiter::tooManyAttempts($this->accountLockoutKey(), self::ACCOUNT_MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->accountLockoutKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the per-request (email + IP) throttle key.
     *
     * @return string
     */
    public function throttleKey()
    {
        return Str::lower($this->input('email')).'|'.$this->ip();
    }

    /**
     * Get the account-level lockout key (ignores IP — blocks distributed
     * brute-force across multiple source IPs targeting the same account).
     *
     * @return string
     */
    public function accountLockoutKey()
    {
        return 'login:account:'.Str::lower($this->input('email'));
    }
}
