<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;

    private Company $b;

    private User $adminA;

    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Company::factory()->create();
        $this->b = Company::factory()->create();
        $this->adminA = $this->user($this->a);
        $this->adminB = $this->user($this->b);
    }

    public function test_admin_crud_status_search_pagination_and_ownership_protection(): void
    {
        $token = $this->token($this->adminA);
        $response = $this->withToken($token)->postJson('/api/v1/suppliers', ['name' => ' Acme Supply ', 'contact_person' => 'Ali', 'phone' => '+92 300 1234567', 'email' => 'a@example.com', 'company_id' => $this->b->id, 'created_by' => $this->adminB->id])->assertUnprocessable()->assertJsonValidationErrors(['company_id', 'created_by']);
        $response = $this->withToken($token)->postJson('/api/v1/suppliers', ['name' => ' Acme Supply ', 'contact_person' => 'Ali', 'phone' => '+92 300 1234567', 'email' => 'a@example.com'])->assertCreated()->assertJsonPath('data.name', 'Acme Supply');
        $id = $response->json('data.id');
        $this->withToken($token)->patchJson("/api/v1/suppliers/{$id}", ['phone' => '123'])->assertOk();
        $this->withToken($token)->patchJson("/api/v1/suppliers/{$id}/status", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
        $this->withToken($token)->getJson('/api/v1/suppliers?search=Acme&is_active=0&per_page=1')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_supplier_is_tenant_scoped_soft_deleted_and_logged(): void
    {
        $s = $this->supplier($this->a, $this->adminA);
        $this->withToken($this->token($this->adminB))->getJson("/api/v1/suppliers/{$s->id}")->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->adminA))->deleteJson("/api/v1/suppliers/{$s->id}")->assertOk();
        $this->assertSoftDeleted('suppliers', ['id' => $s->id]);
        $this->withToken($this->token($this->adminA))->getJson('/api/v1/suppliers')->assertOk()->assertJsonPath('meta.total', 0);
        $events = DB::table('activity_log')->where('event', 'supplier.deleted')->count();
        $this->assertSame(1, $events);
    }

    private function user(Company $c): User
    {
        $u = User::factory()->for($c)->create(['status' => 'Active']);
        $this->grantRole($u, 'company_admin');

        return $u;
    }

    private function token(User $u): string
    {
        return $u->createToken('supplier-test')->plainTextToken;
    }

    private function supplier(Company $c, User $u): Supplier
    {
        return Supplier::forceCreate(['company_id' => $c->id, 'name' => 'Supplier', 'is_active' => true, 'created_by' => $u->id]);
    }
}
