<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\PasswordRecoveryResetRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService)
    {
    }
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        $role = $user->roles->first();
        $permissions = $user->getAllPermissions()->pluck('name')->toArray();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'customer_id' => $user->customer_id,
            ],
            'role' => $role ? $role->name : null,
            'permissions' => $permissions,
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        $role = $user->roles->first();
        $permissions = $user->getAllPermissions()->pluck('name')->toArray();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'customer_id' => $user->customer_id,
            ],
            'role' => $role ? $role->name : null,
            'permissions' => $permissions,
        ]);
    }

    public function validateToken(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid or expired token',
            ], 401);
        }

        $role = $user->roles->first();
        $permissions = $user->getAllPermissions()->pluck('name')->toArray();

        return response()->json([
            'valid' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'customer_id' => $user->customer_id,
            ],
            'role' => $role ? $role->name : null,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Get enabled authentication resources
     */
    public function getAuthResources(): JsonResponse
    {
        $resources = $this->authService->getEnabledAuthResources();

        return response()->json($resources);
    }

    /**
     * Register a new user
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        if (!config('application.resources.auth.self_register')) {
            return response()->json([
                'message' => 'Registration is disabled',
            ], 403);
        }

        $token = $this->authService->createEmailVerificationToken(
            $request->input('email'),
            'registration'
        );

        $this->authService->sendRegistrationVerificationEmail(
            $token,
            $request->input('name')
        );

        return response()->json([
            'message' => 'Verification email has been sent',
            'email' => $request->input('email'),
        ], 201);
    }

    /**
     * Verify email token during registration
     */
    public function registerVerifyEmail(Request $request): JsonResponse
    {
        if (!config('application.resources.auth.self_register')) {
            return response()->json([
                'message' => 'Registration is disabled',
            ], 403);
        }

        $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required_without:hash', 'nullable', 'string', 'size:6'],
            'hash' => ['required_without:code', 'nullable', 'string'],
        ]);

        $email = $request->input('email');
        $code = $request->input('code');
        $hash = $request->input('hash');

        $token = null;

        if ($code) {
            $token = $this->authService->verifyTokenByCode($email, $code);
        } elseif ($hash) {
            $token = $this->authService->verifyTokenByHash($hash);
        }

        if (!$token) {
            return response()->json([
                'message' => 'Invalid or expired verification token',
            ], 422);
        }

        return response()->json([
            'message' => 'Email verified successfully',
            'token' => $token->hash,
        ]);
    }

    /**
     * Complete registration after email verification
     */
    public function registerComplete(Request $request): JsonResponse
    {
        if (!config('application.resources.auth.self_register')) {
            return response()->json([
                'message' => 'Registration is disabled',
            ], 403);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'verification_token' => ['required', 'string'],
        ]);

        $token = $this->authService->verifyTokenByHash(
            $request->input('verification_token')
        );

        if (!$token || $token->email !== $request->input('email')) {
            return response()->json([
                'message' => 'Invalid verification token',
            ], 422);
        }

        $user = $this->authService->createUserFromVerification($token, [
            'name' => $request->input('name'),
            'password' => $request->input('password'),
        ]);

        $apiToken = $user->createToken('auth-token')->plainTextToken;
        $role = $user->roles->first();
        $permissions = $user->getAllPermissions()->pluck('name')->toArray();

        return response()->json([
            'message' => 'Registration completed successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'customer_id' => $user->customer_id,
            ],
            'role' => $role ? $role->name : null,
            'permissions' => $permissions,
            'token' => $apiToken,
        ], 201);
    }

    /**
     * Request password recovery
     */
    public function requestPasswordRecovery(Request $request): JsonResponse
    {
        if (!config('application.resources.auth.self_recovery_password')) {
            return response()->json([
                'message' => 'Password recovery is disabled',
            ], 403);
        }

        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $token = $this->authService->createEmailVerificationToken(
            $request->input('email'),
            'password_reset'
        );

        $this->authService->sendPasswordRecoveryEmail($token);

        return response()->json([
            'message' => 'If the email exists, a recovery link will be sent',
            'email' => $request->input('email'),
        ]);
    }

    /**
     * Verify password recovery token
     */
    public function verifyPasswordRecoveryToken(Request $request): JsonResponse
    {
        if (!config('application.resources.auth.self_recovery_password')) {
            return response()->json([
                'message' => 'Password recovery is disabled',
            ], 403);
        }

        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'code' => ['required_without:hash', 'nullable', 'string', 'size:6'],
            'hash' => ['required_without:code', 'nullable', 'string'],
        ]);

        $email = $request->input('email');
        $code = $request->input('code');
        $hash = $request->input('hash');

        $token = null;

        if ($code) {
            $token = $this->authService->verifyTokenByCode($email, $code);
        } elseif ($hash) {
            $token = $this->authService->verifyTokenByHash($hash);
        }

        if (!$token || $token->type !== 'password_reset') {
            return response()->json([
                'message' => 'Invalid or expired recovery token',
            ], 422);
        }

        return response()->json([
            'message' => 'Token verified successfully',
            'token' => $token->hash,
        ]);
    }

    /**
     * Reset password
     */
    public function resetPassword(PasswordRecoveryResetRequest $request): JsonResponse
    {
        if (!config('application.resources.auth.self_recovery_password')) {
            return response()->json([
                'message' => 'Password recovery is disabled',
            ], 403);
        }

        $email = $request->input('email');
        $code = $request->input('code');
        $hash = $request->input('hash');

        $token = null;

        if ($code) {
            $token = $this->authService->verifyTokenByCode($email, $code);
        } elseif ($hash) {
            $token = $this->authService->verifyTokenByHash($hash);
        }

        if (!$token || $token->type !== 'password_reset') {
            return response()->json([
                'message' => 'Invalid or expired recovery token',
            ], 422);
        }

        $user = $this->authService->resetUserPassword(
            $token,
            $request->input('password')
        );

        return response()->json([
            'message' => 'Password has been reset successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Change password for authenticated user
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $success = $this->authService->changePassword(
            $user,
            $request->input('current_password'),
            $request->input('password')
        );

        if (!$success) {
            return response()->json([
                'message' => 'Current password is incorrect',
            ], 422);
        }

        return response()->json([
            'message' => 'Password has been changed successfully',
        ]);
    }
}
