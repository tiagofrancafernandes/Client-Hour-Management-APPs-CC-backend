<?php

namespace Tests\Feature;

use App\Models\EmailVerificationToken;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = app(AuthService::class);
    }

    /**
     * Test that registration is disabled when configured
     */
    public function testRegistrationDisabledWhenNotConfigured(): void
    {
        config(['application.resources.auth.self_register' => false]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Registration is disabled']);
    }

    /**
     * Test registration request creates verification token
     */
    public function testRegistrationRequestCreatesVerificationToken(): void
    {
        config(['application.resources.auth.self_register' => true]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'message' => 'Verification email has been sent',
            'email' => 'test@example.com',
        ]);

        $token = EmailVerificationToken::where('email', 'test@example.com')
            ->where('type', 'registration')
            ->first();

        $this->assertNotNull($token);
        $this->assertTrue($token->isValid());
    }

    /**
     * Test registration validation
     */
    public function testRegistrationValidationFailsWithInvalidData(): void
    {
        config(['application.resources.auth.self_register' => true]);

        $response = $this->postJson('/api/auth/register', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    /**
     * Test email verification with code
     */
    public function testEmailVerificationWithCode(): void
    {
        config(['application.resources.auth.self_register' => true]);

        $email = 'test@example.com';
        $token = $this->authService->createEmailVerificationToken($email, 'registration');

        $response = $this->postJson('/api/auth/register/verify', [
            'email' => $email,
            'code' => $token->token,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Email verified successfully',
        ]);
        $response->assertJsonStructure(['token']);
    }

    /**
     * Test email verification with hash
     */
    public function testEmailVerificationWithHash(): void
    {
        config(['application.resources.auth.self_register' => true]);

        $email = 'test@example.com';
        $token = $this->authService->createEmailVerificationToken($email, 'registration');

        $response = $this->postJson('/api/auth/register/verify', [
            'email' => $email,
            'hash' => $token->hash,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Email verified successfully',
        ]);
    }

    /**
     * Test email verification fails with invalid code
     */
    public function testEmailVerificationFailsWithInvalidCode(): void
    {
        config(['application.resources.auth.self_register' => true]);

        $response = $this->postJson('/api/auth/register/verify', [
            'email' => 'test@example.com',
            'code' => '000000',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Invalid or expired verification token']);
    }

    /**
     * Test complete registration
     */
    public function testCompleteRegistration(): void
    {
        config(['application.resources.auth.self_register' => true]);

        $email = 'test@example.com';
        $token = $this->authService->createEmailVerificationToken($email, 'registration');

        $response = $this->postJson('/api/auth/register/complete', [
            'name' => 'Test User',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'verification_token' => $token->hash,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'user' => ['id', 'name', 'email'],
            'token',
        ]);

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->email_verified_at !== null);

        $tokenRecord = EmailVerificationToken::where('email', $email)->first();
        $this->assertNull($tokenRecord);
    }

    /**
     * Test get auth resources endpoint
     */
    public function testGetAuthResources(): void
    {
        config(['application.resources.auth.self_register' => true]);
        config(['application.resources.auth.self_recovery_password' => false]);

        $response = $this->getJson('/api/auth/resources');

        $response->assertStatus(200);
        $response->assertJson([
            'self_register' => true,
            'self_recovery_password' => false,
        ]);
    }
}
