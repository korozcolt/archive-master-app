<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear empresa de prueba
        $this->company = Company::factory()->create();

        // Crear usuario de prueba
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);
    }

    /** @test */
    public function user_can_login_with_valid_credentials()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'token',
                        'token_type',
                        'expires_in',
                        'user' => [
                            'id',
                            'name',
                            'email',
                            'company_id',
                        ]
                    ],
                    'timestamp'
                ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals('Bearer', $response->json('data.token_type'));
    }

    /** @test */
    public function user_cannot_login_with_invalid_credentials()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
                ->assertJson([
                    'success' => false,
                    'message' => 'Credenciales inválidas',
                ]);
    }

    /** @test */
    public function login_requires_email_and_password()
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email', 'password']);
    }

    /** @test */
    public function authenticated_user_can_get_profile()
    {
        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/auth/me');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'name',
                        'email',
                        'company_id',
                        'company',
                        'roles',
                        'permissions',
                    ],
                    'timestamp'
                ]);

        $this->assertEquals($this->user->id, $response->json('data.id'));
    }

    /** @test */
    public function authenticated_user_can_logout()
    {
        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/auth/logout');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Logout exitoso',
                ]);
    }

    /** @test */
    public function authenticated_user_can_refresh_token()
    {
        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/auth/refresh');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'token',
                        'token_type',
                        'expires_in',
                    ],
                    'timestamp'
                ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_protected_routes()
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }
}
