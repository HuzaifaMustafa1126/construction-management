<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VersionedApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_expected_json(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Construction ERP API is running',
                'version' => 'v1',
            ]);
    }

    public function test_valid_login_returns_a_token(): void
    {
        $user = $this->user('admin', ['password' => Hash::make('password123')]);

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonPath('message', 'Login successful')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['token'])
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.remember_token');

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_invalid_login_returns_unauthorized(): void
    {
        $user = $this->user('admin', ['password' => Hash::make('password123')]);

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ])->assertUnauthorized()
            ->assertExactJson(['message' => 'Invalid email or password.']);
    }

    public function test_invalid_login_payload_returns_validation_errors(): void
    {
        $this->postJson('/api/v1/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_me_returns_the_authenticated_user_without_sensitive_fields(): void
    {
        $user = $this->user('admin');

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.remember_token');
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = $this->user('admin');
        $token = $this->tokenFor($user);

        $this->withToken($token)
            ->postJson('/api/v1/logout')
            ->assertOk()
            ->assertExactJson(['message' => 'Logout successful']);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertUnauthorized();
    }

    public function test_admin_can_list_users(): void
    {
        $admin = $this->user('admin');

        $this->withToken($this->tokenFor($admin))
            ->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonStructure(['users']);
    }

    public function test_supervisor_cannot_list_users(): void
    {
        $supervisor = $this->user('supervisor');

        $this->withToken($this->tokenFor($supervisor))
            ->getJson('/api/v1/users')
            ->assertForbidden();
    }

    public function test_admin_can_create_a_user(): void
    {
        $admin = $this->user('admin');

        $this->withToken($this->tokenFor($admin))
            ->postJson('/api/v1/users', $this->newUserPayload())
            ->assertCreated()
            ->assertJsonPath('message', 'User created successfully.')
            ->assertJsonPath('user.email', 'ayesha@example.com')
            ->assertJsonPath('user.status', 'Active');

        $this->assertDatabaseHas('users', [
            'name' => 'Ayesha Khan',
            'email' => 'ayesha@example.com',
            'role' => 'supervisor',
        ]);
    }

    public function test_supervisor_cannot_create_users(): void
    {
        $supervisor = $this->user('supervisor');

        $this->withToken($this->tokenFor($supervisor))
            ->postJson('/api/v1/users', $this->newUserPayload())
            ->assertForbidden();
    }

    public function test_invalid_user_creation_returns_validation_errors(): void
    {
        $admin = $this->user('admin');

        $this->withToken($this->tokenFor($admin))
            ->postJson('/api/v1/users', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'first_name', 'last_name', 'email', 'password', 'role',
            ]);
    }

    public function test_admin_can_delete_another_user(): void
    {
        $admin = $this->user('admin');
        $target = $this->user('client', [], $admin->company);

        $this->withToken($this->tokenFor($admin))
            ->deleteJson("/api/v1/users/{$target->id}")
            ->assertOk()
            ->assertExactJson(['message' => 'User deleted successfully.']);

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->user('admin');

        $this->withToken($this->tokenFor($admin))
            ->deleteJson("/api/v1/users/{$admin->id}")
            ->assertForbidden()
            ->assertExactJson(['message' => 'You cannot delete your own account.']);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_unauthenticated_user_management_requests_return_unauthorized(): void
    {
        $this->getJson('/api/v1/users')->assertUnauthorized();
        $this->postJson('/api/v1/users', $this->newUserPayload())->assertUnauthorized();
        $this->deleteJson('/api/v1/users/999')->assertUnauthorized();
    }

    public function test_legacy_endpoints_remain_compatible(): void
    {
        $admin = $this->user('admin', ['password' => Hash::make('password123')]);

        $login = $this->postJson('/api/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ])->assertOk()->assertJsonStructure(['user', 'token']);

        $token = $login->json('token');

        $this->withToken($token)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.id', $admin->id);

        $this->withToken($token)->getJson('/api/users')
            ->assertOk()
            ->assertJsonStructure(['users']);
    }

    private function user(string $role, array $attributes = [], ?Company $company = null): User
    {
        $company ??= Company::factory()->create();

        return User::factory()->for($company)->create(array_merge(['role' => $role], $attributes));
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function newUserPayload(): array
    {
        return [
            'first_name' => 'Ayesha',
            'last_name' => 'Khan',
            'email' => 'ayesha@example.com',
            'phone' => '03001234567',
            'password' => 'secure-password',
            'role' => 'supervisor',
            'department' => 'Site Operations',
        ];
    }
}
