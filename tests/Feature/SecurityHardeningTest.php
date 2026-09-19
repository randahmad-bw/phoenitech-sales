<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the Phase 0 hardening so a later change cannot silently undo it.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function login_is_throttled_after_repeated_failures()
    {
        User::factory()->create([
            'email' => 'target@phoenitech.com',
            'password' => bcrypt('correct-password'),
        ]);

        // The limiter allows 5 attempts per minute per email + IP.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('auth.login'), [
                'email' => 'target@phoenitech.com',
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        $this->postJson(route('auth.login'), [
            'email' => 'target@phoenitech.com',
            'password' => 'wrong-password',
        ])->assertStatus(429)
            ->assertJson([
                'success' => false,
                'error_code' => 'TOO_MANY_LOGIN_ATTEMPTS',
            ]);
    }

    /** @test */
    public function throttling_a_single_account_does_not_lock_out_other_users()
    {
        User::factory()->create([
            'email' => 'victim@phoenitech.com',
            'password' => bcrypt('correct-password'),
        ]);

        for ($i = 0; $i < 6; $i++) {
            $this->postJson(route('auth.login'), [
                'email' => 'attacked@phoenitech.com',
                'password' => 'wrong-password',
            ]);
        }

        // Keyed per email + IP, so an unrelated account still authenticates.
        $this->postJson(route('auth.login'), [
            'email' => 'victim@phoenitech.com',
            'password' => 'correct-password',
        ])->assertStatus(200);
    }

    /** @test */
    public function cors_never_allows_a_wildcard_origin()
    {
        $origins = config('cors.allowed_origins');

        $this->assertNotContains('*', $origins);
        $this->assertTrue(config('cors.supports_credentials'));
    }

    /** @test */
    public function issued_tokens_carry_an_expiry()
    {
        $this->assertNotNull(config('sanctum.expiration'));
        $this->assertGreaterThan(0, config('sanctum.expiration'));
    }
}
