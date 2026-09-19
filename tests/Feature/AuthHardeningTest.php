<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 3 auth hardening: email/username login, disabled-account rejection,
 * last-login capture, and self-service password change.
 */
class AuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_user_can_log_in_with_their_username()
    {
        User::factory()->create([
            'username' => 'johnny',
            'password' => 'secret123',
        ]);

        $this->postJson(route('auth.login'), ['email' => 'johnny', 'password' => 'secret123'])
            ->assertStatus(200)
            ->assertJsonPath('data.user.username', 'johnny');
    }

    /** @test */
    public function a_user_can_still_log_in_with_their_email()
    {
        User::factory()->create([
            'email' => 'person@phoenitech.sy',
            'password' => 'secret123',
        ]);

        $this->postJson(route('auth.login'), ['email' => 'person@phoenitech.sy', 'password' => 'secret123'])
            ->assertStatus(200);
    }

    /** @test */
    public function a_disabled_account_cannot_log_in()
    {
        User::factory()->create([
            'email' => 'blocked@phoenitech.sy',
            'password' => 'secret123',
            'is_active' => false,
        ]);

        $this->postJson(route('auth.login'), ['email' => 'blocked@phoenitech.sy', 'password' => 'secret123'])
            ->assertStatus(403)
            ->assertJson(['error_code' => 'FORBIDDEN']);
    }

    /** @test */
    public function login_records_last_login_metadata()
    {
        $user = User::factory()->create([
            'email' => 'trace@phoenitech.sy',
            'password' => 'secret123',
        ]);

        $this->assertNull($user->last_login_at);

        $this->postJson(route('auth.login'), ['email' => 'trace@phoenitech.sy', 'password' => 'secret123'])
            ->assertStatus(200);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    /** @test */
    public function changing_password_requires_the_correct_current_password()
    {
        $user = User::factory()->create(['password' => 'oldpass123']);
        $token = $user->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'wrong-one',
                'password' => 'NewPass123',
                'password_confirmation' => 'NewPass123',
            ])
            ->assertStatus(422)
            ->assertJson(['error_code' => 'INVALID_CURRENT_PASSWORD']);
    }

    /** @test */
    public function a_weak_new_password_is_rejected()
    {
        $user = User::factory()->create(['password' => 'oldpass123']);
        $token = $user->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'oldpass123',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertStatus(422);
    }

    /** @test */
    public function a_user_can_change_their_password_and_other_sessions_are_revoked()
    {
        $user = User::factory()->create(['password' => 'oldpass123']);
        $keptToken = $user->createToken('current')->plainTextToken;
        $otherToken = $user->createToken('other');

        $this->withHeader('Authorization', 'Bearer '.$keptToken)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'oldpass123',
                'password' => 'NewPass123',
                'password_confirmation' => 'NewPass123',
            ])
            ->assertStatus(200);

        // New password works, old one does not.
        $this->assertTrue(Hash::check('NewPass123', $user->fresh()->password));

        // The other session's token was revoked; the current one survives.
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $otherToken->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'current']);
    }
}
