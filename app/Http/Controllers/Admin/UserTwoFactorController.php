<?php

namespace App\Http\Controllers\Admin;

use App\Aine\AuditLogger;
use App\Aine\TwoFactor;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Admin-side two-factor authentication management, mirroring the self-service
 * flow of Auth\TwoFactorController but acting on any user id. Only
 * super_admin may call these — enforced by the `role:super_admin` route
 * middleware. The admin acting on their own account goes through the same
 * endpoints (super admin privileges back the action).
 */
class UserTwoFactorController extends Controller
{
    /** Generate (or reuse) a 2FA secret for the target user. */
    public function enable(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        if ($user->twoFactorEnabled()) {
            return response()->json(['message' => __('Two factor authentication is already enabled.')], 422);
        }

        if (! $user->two_factor_secret) {
            $user->two_factor_secret = TwoFactor::generateSecret();
            $user->save();
        }

        return response()->json([
            'secret' => $user->two_factor_secret,
            'provisioning_uri' => TwoFactor::provisioningUri($user->two_factor_secret, $user->email),
        ], 200);
    }

    /** Verify a code and fully enable 2FA for the target user. */
    public function confirm(Request $request, int $id)
    {
        $request->validate(['code' => 'required|string']);

        $user = User::findOrFail($id);

        if (! $user->two_factor_secret) {
            return response()->json(['message' => __('Enable two factor authentication first.')], 422);
        }

        if (! TwoFactor::verify($user->two_factor_secret, $request->input('code'))) {
            return response()->json(['message' => __('The provided code was invalid.')], 422);
        }

        $codes = TwoFactor::generateRecoveryCodes();
        $user->setTwoFactorRecoveryCodes($codes);
        $user->two_factor_confirmed_at = now();
        $user->save();

        AuditLogger::log('update', 'user', $user->id, $user->email, ['field' => '2fa', 'action' => 'enable']);

        return response()->json(['recovery_codes' => $codes], 200);
    }

    /** Disable 2FA for the target user (admin action, no user password needed). */
    public function disable(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        if (! $user->twoFactorEnabled()) {
            return response()->json(['message' => __('Two factor authentication is not enabled.')], 422);
        }

        $user->disableTwoFactor();

        AuditLogger::log('update', 'user', $user->id, $user->email, ['field' => '2fa', 'action' => 'disable']);

        return response()->json(['message' => __('Two factor authentication has been disabled.')], 200);
    }

    /** View or regenerate the recovery codes of the target user. */
    public function recoveryCodes(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        if (! $user->twoFactorEnabled()) {
            return response()->json(['message' => __('Two factor authentication is not enabled.')], 422);
        }

        $codes = TwoFactor::generateRecoveryCodes();
        $user->setTwoFactorRecoveryCodes($codes);
        $user->save();

        AuditLogger::log('update', 'user', $user->id, $user->email, ['field' => '2fa', 'action' => 'recovery-codes']);

        return response()->json(['recovery_codes' => $codes], 200);
    }
}
