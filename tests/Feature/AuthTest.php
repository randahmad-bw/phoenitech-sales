<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'admin@phoenitech.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson(route('auth.login'), [
            'email' => 'admin@phoenitech.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'email'],
                ],
            ]);
    }

    /** @test */
    public function user_cannot_login_with_invalid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'admin@phoenitech.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson(route('auth.login'), [
            'email' => 'admin@phoenitech.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid email or password.',
                'error_code' => 'UNAUTHORIZED',
            ]);
    }

    /** @test */
    public function authenticated_user_can_access_me_endpoint()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson(route('auth.me'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'name', 'email'],
            ]);
    }

    /** @test */
    public function authenticated_user_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson(route('auth.logout'));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully.',
            ]);
    }
}
