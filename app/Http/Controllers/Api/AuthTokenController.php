<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

/**
 * Issues and revokes API tokens for the admin mobile app. The app sends the
 * password first; if the account has two-factor authentication, it is told
 * so and sends the password again with a code, so a token never skips 2FA.
 */
class AuthTokenController extends Controller
{
    /**
     * How long a mobile token stays valid before the admin must sign in again.
     */
    protected const TOKEN_LIFETIME_DAYS = 30;

    public function store(Request $request, TwoFactorAuthenticationProvider $twoFactor): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => __('Your account has been deactivated. Please contact the administrator.')]);
        }

        if (! $user->isAdmin() || ! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['email' => __('The mobile app is only available to administrators with a verified email address.')]);
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            if (blank($data['code'] ?? null) && blank($data['recovery_code'] ?? null)) {
                return response()->json(['two_factor' => true]);
            }

            $this->verifyTwoFactor($user, $twoFactor, $data['code'] ?? null, $data['recovery_code'] ?? null);
        }

        $token = $user->createToken($data['device_name'], ['monitor'], now()->addDays(self::TOKEN_LIFETIME_DAYS));

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => new UserResource($user),
        ], Response::HTTP_CREATED);
    }

    /**
     * Sign the device out by revoking the token it used.
     */
    public function destroy(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * Mirror Fortify's challenge: a TOTP code, or a one-time recovery code that
     * is then replaced so it cannot be used again.
     */
    protected function verifyTwoFactor(User $user, TwoFactorAuthenticationProvider $twoFactor, ?string $code, ?string $recoveryCode): void
    {
        if (filled($code)) {
            if ($twoFactor->verify(Fortify::currentEncrypter()->decrypt($user->two_factor_secret), $code)) {
                return;
            }

            throw ValidationException::withMessages(['code' => __('The provided two factor authentication code was invalid.')]);
        }

        $match = collect($user->recoveryCodes())->first(fn (string $stored) => hash_equals($stored, (string) $recoveryCode));

        if ($match === null) {
            throw ValidationException::withMessages(['recovery_code' => __('The provided two factor recovery code was invalid.')]);
        }

        $user->replaceRecoveryCode($match);
    }
}
