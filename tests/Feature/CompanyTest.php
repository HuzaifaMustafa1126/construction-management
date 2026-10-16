<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_companies_table_has_the_required_schema(): void
    {
        $this->assertTrue(Schema::hasTable('companies'));
        $this->assertTrue(Schema::hasColumns('companies', [
            'id',
            'name',
            'slug',
            'logo',
            'email',
            'phone',
            'address',
            'city',
            'country',
            'currency',
            'timezone',
            'subscription_plan_id',
            'subscription_status',
            'trial_ends_at',
            'status',
            'created_at',
            'updated_at',
            'deleted_at',
        ]));
        $this->assertTrue(Schema::hasColumn('users', 'company_id'));
        $this->assertTrue(Schema::hasIndex('users', ['company_id']));
    }

    public function test_company_can_be_created_and_slug_must_be_unique(): void
    {
        Company::create([
            'name' => 'North Star Construction',
            'slug' => 'north-star-construction',
        ]);

        $this->assertDatabaseHas('companies', [
            'slug' => 'north-star-construction',
            'currency' => 'PKR',
            'timezone' => 'Asia/Karachi',
            'subscription_status' => 'trial',
            'status' => 'active',
        ]);

        $this->expectException(QueryException::class);

        Company::create([
            'name' => 'Duplicate Slug Company',
            'slug' => 'north-star-construction',
        ]);
    }

    public function test_company_has_many_users_and_user_belongs_to_company(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();

        $this->assertTrue($company->users->contains($user));
        $this->assertTrue($user->company->is($company));
    }

    public function test_users_can_remain_valid_without_a_company(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->company_id);
        $this->assertNull($user->company);
    }

    public function test_soft_deleting_a_company_does_not_delete_its_users(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();

        $company->delete();

        $this->assertSoftDeleted($company);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_database_restricts_hard_deletion_while_users_are_assigned(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();

        try {
            $company->forceDelete();
            $this->fail('Expected the company foreign key to restrict deletion.');
        } catch (QueryException) {
            $this->assertDatabaseHas('companies', ['id' => $company->id]);
            $this->assertDatabaseHas('users', ['id' => $user->id]);
        }
    }

    public function test_company_factory_supports_active_suspended_and_trial_states(): void
    {
        $active = Company::factory()->active()->create();
        $suspended = Company::factory()->suspended()->create();
        $trial = Company::factory()->trial()->create();

        $this->assertSame(Company::STATUS_ACTIVE, $active->status);
        $this->assertSame(Company::STATUS_SUSPENDED, $suspended->status);
        $this->assertSame(Company::SUBSCRIPTION_STATUS_TRIAL, $trial->subscription_status);
        $this->assertNotNull($trial->trial_ends_at);
        $this->assertNotSame($active->slug, $suspended->slug);
    }

    public function test_existing_user_creation_api_cannot_inject_company_id(): void
    {
        $adminCompany = Company::factory()->create();
        $admin = User::factory()->for($adminCompany)->create(['role' => 'admin']);
        $otherCompany = Company::factory()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/users', [
            'first_name' => 'Ayesha',
            'last_name' => 'Khan',
            'email' => 'ayesha@example.com',
            'phone' => '03001234567',
            'password' => 'secure-password',
            'role' => 'supervisor',
            'department' => 'Site Operations',
            'company_id' => $otherCompany->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['company_id']);

        $this->assertDatabaseMissing('users', [
            'email' => 'ayesha@example.com',
        ]);
    }

    public function test_company_assignment_command_is_explicit_and_idempotent(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();

        $this->artisan('company:assign-users', [
            'company' => $company->id,
            'user_ids' => [$user->id],
        ])->assertSuccessful();
        $this->assertNull($user->fresh()->company_id);

        $this->artisan('company:assign-users', [
            'company' => $company->id,
            'user_ids' => [$user->id],
            '--commit' => true,
        ])->assertSuccessful();
        $this->assertSame($company->id, $user->fresh()->company_id);

        $this->artisan('company:assign-users', [
            'company' => $company->id,
            'user_ids' => [$user->id],
            '--commit' => true,
        ])->assertSuccessful();
        $this->assertSame($company->id, $user->fresh()->company_id);
    }
}
