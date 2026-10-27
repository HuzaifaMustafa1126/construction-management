<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_company_registration_is_retired_without_writes(): void
    {
        $this->postJson('/api/v1/register-company', $this->payload())->assertNotFound();
        $this->assertDatabaseCount('companies', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_company_administrator_cannot_create_another_company(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id, 'status' => User::STATUS_ACTIVE]);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/platform/companies', $this->payload())->assertForbidden();
        $this->assertDatabaseCount('companies', 1);
    }

    public function test_only_platform_administrator_can_reach_company_creation_validation(): void
    {
        $admin = User::factory()->create(['company_id' => null, 'status' => User::STATUS_ACTIVE, 'is_platform_admin' => true]);
        Sanctum::actingAs($admin, ['platform']);
        $this->postJson('/api/v1/platform/companies', [])->assertUnprocessable()->assertJsonValidationErrors(['company', 'admin']);
    }

    private function payload(): array
    {
        return ['company'=>['name'=>'ABC Construction','email'=>'info@example.test','phone'=>'+923001234567','address'=>'Lahore'],'admin'=>['first_name'=>'Ali','last_name'=>'Ahmed','email'=>'ali@example.test','phone'=>'+923009876543']];
    }
}
