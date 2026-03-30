<?php

namespace Tests\Feature;

use App\Models\EmailVerificationToken;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthPasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = app(AuthService::class);
    }

    /**
     * Test password recovery disabled when not configured
     */
    public function testPasswordRecoveryDisabledWhenNotConfigured(): void
    {
        config(['application.resources.auth.self_recovery_password' => false]);

        $response = $this->postJson('/api/auth/password-recovery/request', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Password recovery is disabled']);
    }

    /**
     * Test password recovery request
     */
    public function testPasswordRecoveryRequest(): void
    {
        config(['application.resources.auth.self_recovery_password' => true]);

        User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/password-recovery/request', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'If the email exists, a recovery link will be sent',
            'email' => 'test@example.com',
        ]);

        $token = EmailVerificationToken::where('email', 'test@example.com')
            ->where('type', 'password_reset')
            ->first();

        $this->assertNotNull($token);
    }

    /**
     * Test password recovery request fails for non-existent email
     */
    public function testPasswordRecoveryRequestFailsForNonExistentEmail(): void
    {
        config(['application.resources.auth.self_recovery_password' => true]);

        $response = $this->postJson('/api/auth/password-recovery/request', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    /**
     * Test verify password recovery token with code
     */
    public function testVerifyPasswordRecoveryTokenWithCode(): void
    {
        config(['application.resources.auth.self_recovery_password' => true]);

        User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $token = $this->authService->createEmailVerificationToken(
            'test@example.com',
            'password_reset'
        );

        $response = $this->postJson('/api/auth/password-recovery/verify', [
            'email' => 'test@example.com',
            'code' => $token->token,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Token verified successfully',
        ]);
        $response->assertJsonStructure(['token']);
    }

    /**
     * Test verify password recovery token with hash
     */
    public function testVerifyPasswordRecoveryTokenWithHash(): void
    {
        config(['application.resources.auth.self_recovery_password' => true]);

        User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $token = $this->authService->createEmailVerificationToken(
            'test@example.com',
            'password_reset'
        );

        $response = $this->postJson('/api/auth/password-recovery/verify', [
            'email' => 'test@example.com',
            'hash' => $token->hash,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['token']);
    }

    /**
     * Test reset password
     */
    public function testResetPassword(): void
    {
        config(['application.resources.auth.self_recovery_password' => true]);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('oldpassword123'),
        ]);

        $token = $this->authService->createEmailVerificationToken(
            'test@example.com',
            'password_reset'
        );

        $response = $this->postJson('/api/auth/password-recovery/reset', [
            'email' => 'test@example.com',
            'code' => $token->token,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Password has been reset successfully',
        ]);

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));

        $tokenRecord = EmailVerificationToken::where('email', 'test@example.com')->first();
        $this->assertNull($tokenRecord);
    }

    /**
     * Test reset password fails with invalid token
     */
    public function testResetPasswordFailsWithInvalidToken(): void
    {
        config(['application.resources.auth.self_recovery_password' => true]);

        User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/password-recovery/reset', [
            'email' => 'test@example.com',
            'code' => '000000',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Invalid or expired recovery token']);
    }
}
