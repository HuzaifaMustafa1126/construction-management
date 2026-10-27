<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthIdentityContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_backend_authoritative_company_roles_and_permissions(): void
    {
        $company=Company::factory()->active()->create();
        $user=User::factory()->create(['company_id'=>$company->id,'status'=>User::STATUS_ACTIVE]);
        $this->grantRole($user,'site_supervisor');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me')->assertOk()
            ->assertJsonPath('user.company.id',$company->id)
            ->assertJsonPath('user.company.name',$company->name)
            ->assertJsonPath('user.roles.0','site_supervisor')
            ->assertJsonPath('user.effective_permissions',fn($permissions)=>in_array('projects.view',$permissions,true));
    }

    public function test_me_does_not_leak_another_company_identity(): void
    {
        $own=Company::factory()->active()->create(); $other=Company::factory()->active()->create();
        $user=User::factory()->create(['company_id'=>$own->id,'status'=>User::STATUS_ACTIVE]);
        User::factory()->create(['company_id'=>$other->id,'status'=>User::STATUS_ACTIVE]);
        $this->grantRole($user,'client'); Sanctum::actingAs($user);
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('user.company.id',$own->id)->assertJsonMissing(['company'=>['id'=>$other->id]]);
    }
}
