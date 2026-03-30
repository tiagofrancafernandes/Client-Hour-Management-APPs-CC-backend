<?php

namespace App\Services;

use App\Models\EmailVerificationToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthService
{
    public const TOKEN_EXPIRATION_MINUTES = 30;

    /**
     * Generate a numeric token for email verification
     */
    public function generateNumericToken(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a hash for signed URL
     */
    public function generateHash(): string
    {
        return Str::random(60);
    }

    /**
     * Create an email verification token for registration or password recovery
     */
    public function createEmailVerificationToken(
        string $email,
        string $type = 'registration'
    ): EmailVerificationToken {
        $expiresAt = now()->addMinutes(self::TOKEN_EXPIRATION_MINUTES);

        $token = EmailVerificationToken::create([
            'email' => $email,
            'token' => $this->generateNumericToken(),
            'hash' => $this->generateHash(),
            'type' => $type,
            'expires_at' => $expiresAt,
        ]);

        return $token;
    }

    /**
     * Verify email token by 6-digit code
     */
    public function verifyTokenByCode(string $email, string $code): ?EmailVerificationToken
    {
        $token = EmailVerificationToken::where('email', $email)
            ->where('token', $code)
            ->first();

        if (!$token) {
            return null;
        }

        if ($token->isExpired()) {
            $token->delete();

            return null;
        }

        return $token;
    }

    /**
     * Verify email token by hash (signed URL)
     */
    public function verifyTokenByHash(string $hash): ?EmailVerificationToken
    {
        $token = EmailVerificationToken::where('hash', $hash)->first();

        if (!$token) {
            return null;
        }

        if ($token->isExpired()) {
            $token->delete();

            return null;
        }

        return $token;
    }

    /**
     * Create a new user after email verification
     */
    public function createUserFromVerification(
        EmailVerificationToken $token,
        array $userData
    ): User {
        $user = User::create([
            'name' => $userData['name'],
            'email' => $token->email,
            'password' => Hash::make($userData['password']),
            'email_verified_at' => now(),
        ]);

        // Assign default role if configured
        $defaultRole = config('application.resources.auth.default_role_on_register');

        if ($defaultRole) {
            $user->assignRole($defaultRole);
        }

        $token->delete();

        return $user;
    }

    /**
     * Reset user password after email verification
     */
    public function resetUserPassword(EmailVerificationToken $token, string $newPassword): User
    {
        $user = User::where('email', $token->email)->firstOrFail();

        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        $token->delete();

        return $user;
    }

    /**
     * Change password for authenticated user
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): bool
    {
        if (!Hash::check($currentPassword, $user->password)) {
            return false;
        }

        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        return true;
    }

    /**
     * Get enabled authentication resources
     */
    public function getEnabledAuthResources(): array
    {
        $authConfig = config('application.resources.auth');

        return [
            'self_register' => (bool) $authConfig['self_register'],
            'self_recovery_password' => (bool) $authConfig['self_recovery_password'],
        ];
    }
}
