<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_a_sanctum_token(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Login successful')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['token']);

        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_unauthenticated_api_request_returns_json_401(): void
    {
        $this->getJson('/api/users')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_admin_can_list_and_create_users_with_a_bearer_token(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->for($company)->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/users')
            ->assertOk()
            ->assertJsonStructure(['users']);

        $response = $this->withToken($token)->postJson('/api/users', [
            'first_name' => 'Huzaifa',
            'last_name' => 'Mustafa',
            'email' => 'example@gmail.com',
            'phone' => '03000000000',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertCreated()->assertJson([
            'message' => 'User created successfully.',
            'user' => [
                'first_name' => 'Huzaifa',
                'last_name' => 'Mustafa',
                'email' => 'example@gmail.com',
                'phone' => '03000000000',
                'role' => 'admin',
                'status' => 'Active',
            ],
        ])->assertJsonMissing(['password' => 'password123']);

        $this->assertDatabaseHas('users', [
            'email' => 'example@gmail.com',
            'company_id' => $company->id,
        ]);
    }

    public function test_create_user_validation_errors_are_json_422(): void
    {
        $admin = User::factory()->for(Company::factory())->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/users', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'first_name', 'last_name', 'email', 'password', 'role',
            ]);
    }
}
