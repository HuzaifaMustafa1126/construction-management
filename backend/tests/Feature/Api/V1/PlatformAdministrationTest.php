<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_company_registration_is_retired(): void
    {
        $this->postJson('/api/v1/register-company', [])->assertNotFound();
    }

    public function test_company_user_cannot_access_platform_companies(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id'=>$company->id,'status'=>User::STATUS_ACTIVE]);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/platform/companies')->assertForbidden();
    }

    public function test_platform_admin_can_list_companies_without_company_assignment(): void
    {
        Company::factory()->count(2)->create();
        $admin = User::factory()->create(['company_id'=>null,'status'=>User::STATUS_ACTIVE,'is_platform_admin'=>true]);
        Sanctum::actingAs($admin, ['platform']);
        $this->getJson('/api/v1/platform/companies')->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_platform_admin_can_suspend_company_and_revoke_tokens(): void
    {
        $company=Company::factory()->active()->create();
        $member=User::factory()->create(['company_id'=>$company->id,'status'=>User::STATUS_ACTIVE]); $member->createToken('test');
        $admin=User::factory()->create(['company_id'=>null,'status'=>User::STATUS_ACTIVE,'is_platform_admin'=>true]); Sanctum::actingAs($admin,['platform']);
        $this->patchJson("/api/v1/platform/companies/{$company->id}/status",['status'=>'suspended'])->assertOk();
        $this->assertSame('suspended',$company->fresh()->status); $this->assertDatabaseCount('personal_access_tokens',0);
    }

    public function test_platform_admin_can_edit_permitted_company_fields(): void
    {
        $company=Company::factory()->create(); $admin=$this->platformAdmin();
        $this->patchJson("/api/v1/platform/companies/{$company->id}",['name'=>'Updated Builders','email'=>'updated@example.test','phone'=>'+923001112233','address'=>'Islamabad'])->assertOk()->assertJsonPath('data.name','Updated Builders');
        $this->assertSame('Updated Builders',$company->fresh()->name);
    }

    public function test_company_admin_and_anonymous_user_cannot_edit_company(): void
    {
        $company=Company::factory()->create();
        $this->patchJson("/api/v1/platform/companies/{$company->id}",['name'=>'Attack'])->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create(['company_id'=>$company->id,'status'=>User::STATUS_ACTIVE]));
        $this->patchJson("/api/v1/platform/companies/{$company->id}",['name'=>'Attack'])->assertForbidden();
        $this->assertNotSame('Attack',$company->fresh()->name);
    }

    public function test_company_creation_sends_password_setup_notification_and_keeps_admin_inactive(): void
    {
        Notification::fake(); $this->platformAdmin();
        $this->postJson('/api/v1/platform/companies',$this->companyPayload())->assertCreated();
        $administrator=User::query()->where('email','invited@example.test')->firstOrFail();
        $this->assertSame('Inactive',$administrator->status);
        Notification::assertSentTo($administrator,ResetPassword::class);
    }

    public function test_administrator_password_setup_is_single_use_and_activates_for_active_company(): void
    {
        $company=Company::factory()->active()->create();
        $user=User::factory()->create(['company_id'=>$company->id,'status'=>'Inactive','email'=>'setup@example.test','email_verified_at'=>null]);
        $token=Password::createToken($user); $payload=['email'=>$user->email,'token'=>$token,'password'=>'NewStrongPassword1!','password_confirmation'=>'NewStrongPassword1!'];
        $this->postJson('/api/v1/administrator/password/setup',$payload)->assertOk();
        $this->assertTrue(Hash::check('NewStrongPassword1!',$user->fresh()->password)); $this->assertSame('Active',$user->fresh()->status); $this->assertNotNull($user->fresh()->email_verified_at);
        $this->postJson('/api/v1/administrator/password/setup',$payload)->assertUnprocessable()->assertJsonPath('message','This password setup link is invalid or expired.');
    }

    public function test_expired_and_invalid_password_setup_tokens_are_rejected_without_password_change(): void
    {
        $company=Company::factory()->active()->create(); $user=User::factory()->create(['company_id'=>$company->id,'status'=>'Inactive','email'=>'expired@example.test']); $before=$user->password;
        $token=Password::createToken($user); DB::table('password_reset_tokens')->where('email',$user->email)->update(['created_at'=>now()->subMinutes(61)]);
        foreach([$token,'invalid-token'] as $bad){$this->postJson('/api/v1/administrator/password/setup',['email'=>$user->email,'token'=>$bad,'password'=>'NewStrongPassword1!','password_confirmation'=>'NewStrongPassword1!'])->assertUnprocessable()->assertJsonPath('message','This password setup link is invalid or expired.');}
        $this->assertSame($before,$user->fresh()->password);
    }

    public function test_password_setup_does_not_activate_suspended_company_or_administrator(): void
    {
        $company=Company::factory()->suspended()->create(); $user=User::factory()->create(['company_id'=>$company->id,'status'=>'Inactive','email'=>'suspended@example.test']); $token=Password::createToken($user);
        $this->postJson('/api/v1/administrator/password/setup',['email'=>$user->email,'token'=>$token,'password'=>'NewStrongPassword1!','password_confirmation'=>'NewStrongPassword1!'])->assertOk();
        $this->assertSame(Company::STATUS_SUSPENDED,$company->fresh()->status); $this->assertSame('Inactive',$user->fresh()->status);
        $this->postJson('/api/v1/login',['email'=>$user->email,'password'=>'NewStrongPassword1!'])->assertUnauthorized();
    }

    public function test_platform_admin_can_update_own_profile_without_a_company(): void
    {
        $admin = $this->platformAdmin();

        $this->putJson('/api/v1/me', [
            'first_name' => 'Platform',
            'last_name' => 'Owner',
            'email' => 'owner@example.test',
            'phone' => '+923001234567',
        ])->assertOk()->assertJsonPath('user.email', 'owner@example.test');

        $this->assertSame('owner@example.test', $admin->fresh()->email);
    }

    public function test_platform_admin_can_change_password_without_a_company(): void
    {
        $admin = User::factory()->create([
            'company_id' => null,
            'status' => User::STATUS_ACTIVE,
            'is_platform_admin' => true,
            'password' => Hash::make('CurrentPassword1!'),
        ]);
        $token = $admin->createToken('current')->plainTextToken;
        $admin->createToken('other');

        $this->withToken($token)->putJson('/api/v1/me/password', [
            'current_password' => 'CurrentPassword1!',
            'password' => 'NewStrongPassword1!',
            'password_confirmation' => 'NewStrongPassword1!',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewStrongPassword1!', $admin->fresh()->password));
        $this->assertCount(1, $admin->fresh()->tokens);
    }

    private function platformAdmin(): User
    {
        $admin=User::factory()->create(['company_id'=>null,'status'=>User::STATUS_ACTIVE,'is_platform_admin'=>true]); Sanctum::actingAs($admin,['platform']); return $admin;
    }

    private function companyPayload(): array
    {
        return ['company'=>['name'=>'Invited Builders','email'=>'company@example.test','phone'=>'+923001234567','address'=>'Lahore'],'admin'=>['first_name'=>'Invite','last_name'=>'Admin','email'=>'invited@example.test','phone'=>'+923009876543']];
    }
}
