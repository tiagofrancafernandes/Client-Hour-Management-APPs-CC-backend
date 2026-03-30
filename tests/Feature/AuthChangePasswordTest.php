<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('oldpassword123'),
        ]);
    }

    /**
     * Test change password requires authentication
     */
    public function testChangePasswordRequiresAuthentication(): void
    {
        $response = $this->postJson('/api/auth/change-password', [
            'current_password' => 'oldpassword123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test change password fails with invalid current password
     */
    public function testChangePasswordFailsWithInvalidCurrentPassword(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/auth/change-password', [
                'current_password' => 'wrongpassword',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Current password is incorrect']);
    }

    /**
     * Test change password with valid data
     */
    public function testChangePasswordWithValidData(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/auth/change-password', [
                'current_password' => 'oldpassword123',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Password has been changed successfully']);

        $this->user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $this->user->password));
    }

    /**
     * Test change password fails with non-matching confirmation
     */
    public function testChangePasswordFailsWithNonMatchingConfirmation(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/auth/change-password', [
                'current_password' => 'oldpassword123',
                'password' => 'newpassword123',
                'password_confirmation' => 'differentpassword',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    /**
     * Test change password fails with short password
     */
    public function testChangePasswordFailsWithShortPassword(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/auth/change-password', [
                'current_password' => 'oldpassword123',
                'password' => 'short',
                'password_confirmation' => 'short',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }
}
