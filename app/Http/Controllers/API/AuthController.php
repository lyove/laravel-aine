<?php

namespace App\Http\Controllers\API;

use App\Aine\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Lightweight auth helpers:
 * - `me`   : session user for the same-origin SPA (shares the admin web session).
 * - `login`: token login for third-party clients Returns a Sanctum token bound to the platform user account.
 */
class AuthController extends Controller
{
    /** Account-level lockout: max failed attempts per account (across all IPs). */
    private const ACCOUNT_MAX_ATTEMPTS = 10;

    /** Account-level lockout decay in seconds. */
    private const ACCOUNT_DECAY_SECONDS = 600;

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => true,
                'code' => 200,
                'message' => null,
                'data' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => null,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
            ],
        ]);
    }

    /**
     * Token login for the mini program / external clients.
     * POST /api/login  { account: email|name, password [, two_factor_code | two_factor_recovery_code] }
     * Returns { access_token, expired_in (ms), user }
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'account' => ['required', 'string'],
            'password' => ['required', 'string'],
            'two_factor_code' => ['nullable', 'string'],
            'two_factor_recovery_code' => ['nullable', 'string'],
        ]);

        if ($rateLimited = $this->ensureIsNotRateLimited($request)) {
            return $rateLimited;
        }

        if ($accountLocked = $this->ensureAccountNotLocked($request)) {
            return $accountLocked;
        }

        $account = $request->input('account');
        $user = User::where('email', $account)->orWhere('name', $account)->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            RateLimiter::hit($this->throttleKey($request));
            RateLimiter::hit($this->accountLockoutKey($request), self::ACCOUNT_DECAY_SECONDS);

            AuditLogger::log('failed_login', 'user', $user?->id, $account, [
                'ip' => $request->ip(),
                'account_failures' => RateLimiter::attempts($this->accountLockoutKey($request)),
            ]);

            return response()->json([
                'success' => false,
                'code' => 401,
                'message' => '账号或密码错误',
                'data' => null,
            ], 401);
        }

        // Two-factor enabled: never issue a token until the code is verified.
        if ($user->twoFactorEnabled()) {
            $code = (string) $request->input('two_factor_code');
            $recovery = (string) $request->input('two_factor_recovery_code');

            $valid = false;

            if ($code !== '') {
                $valid = $user->verifyTwoFactorCode($code);
            } elseif ($recovery !== '') {
                $valid = $user->verifyTwoFactorRecoveryCode($recovery);
            }

            if (! $valid) {
                RateLimiter::hit($this->throttleKey($request));
                RateLimiter::hit($this->accountLockoutKey($request), self::ACCOUNT_DECAY_SECONDS);

                AuditLogger::log('failed_2fa', 'user', $user->id, $user->email, [
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'code' => 401,
                    'message' => '两步验证码或恢复码无效',
                    'data' => null,
                ], 401);
            }
        }

        RateLimiter::clear($this->throttleKey($request));
        RateLimiter::clear($this->accountLockoutKey($request));

        AuditLogger::log('login', 'user', $user->id, $user->email, ['ip' => $request->ip()]);

        $expiresAt = now()->addDays(30);
        $token = $user->createToken(
            'miniapp',
            ['profile:read', 'profile:write', 'content:read', 'content:write', 'media:read', 'media:write'],
            $expiresAt
        )->plainTextToken;

        $avatar = $user->avatar;
        if ($avatar && ! str_starts_with($avatar, 'http')) {
            $avatar = url($avatar);
        }

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => null,
            'data' => [
                'access_token' => $token,
                'expired_in' => $expiresAt->getTimestamp() * 1000,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $avatar,
                ],
            ],
        ]);
    }

    /**
     * Failure lockout: 5 attempts per minute per {account, IP} — the same
     * policy as the web LoginRequest so the API cannot be brute-forced.
     *
     * @return JsonResponse|null  A 429 response when locked out, null otherwise.
     */
    protected function ensureIsNotRateLimited(Request $request): ?JsonResponse
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return null;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        return response()->json([
            'success' => false,
            'code' => 429,
            'message' => '尝试次数过多，请在 '.ceil($seconds / 60).' 分钟后重试。',
            'data' => null,
        ], 429);
    }

    protected function throttleKey(Request $request): string
    {
        return Str::lower((string) $request->input('account')).'|'.$request->ip();
    }

    /**
     * Account-level lockout: same account failing from any IP.
     *
     * @return JsonResponse|null  A 429 response when locked out, null otherwise.
     */
    protected function ensureAccountNotLocked(Request $request): ?JsonResponse
    {
        $key = $this->accountLockoutKey($request);

        if (! RateLimiter::tooManyAttempts($key, self::ACCOUNT_MAX_ATTEMPTS)) {
            return null;
        }

        $seconds = RateLimiter::availableIn($key);

        return response()->json([
            'success' => false,
            'code' => 429,
            'message' => '账号尝试次数过多，请在 '.ceil($seconds / 60).' 分钟后重试。',
            'data' => null,
        ], 429);
    }

    protected function accountLockoutKey(Request $request): string
    {
        return 'login:account:'.Str::lower((string) $request->input('account'));
    }
}
