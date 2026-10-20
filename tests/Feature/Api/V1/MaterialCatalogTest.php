<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MeasurementUnit;
use App\Models\User;
use Database\Seeders\MeasurementUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaterialCatalogTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;

    private Company $b;

    private User $adminA;

    private User $adminB;

    private User $supervisor;

    private User $client;

    private MeasurementUnit $systemUnit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Company::factory()->create();
        $this->b = Company::factory()->create();
        $this->adminA = $this->user($this->a, 'company_admin');
        $this->adminB = $this->user($this->b, 'company_admin');
        $this->supervisor = $this->user($this->a, 'site_supervisor');
        $this->client = $this->user($this->a, 'client');
        $this->seed(MeasurementUnitSeeder::class);
        $this->systemUnit = MeasurementUnit::where('symbol', 'kg')->firstOrFail();
    }

    public function test_system_units_are_shared_immutable_and_seeding_is_idempotent(): void
    {
        $this->seed(MeasurementUnitSeeder::class);
        $this->assertDatabaseCount('measurement_units', 9);
        foreach ([$this->adminA, $this->adminB] as $admin) {
            $this->withToken($this->token($admin))->getJson('/api/v1/measurement-units')->assertOk()->assertJsonFragment(['symbol' => 'kg', 'is_system' => true]);
            $this->app['auth']->forgetGuards();
        }
        $url = "/api/v1/measurement-units/{$this->systemUnit->id}";
        $this->withToken($this->token($this->adminA))->patchJson($url, ['name' => 'Changed'])->assertForbidden();
        $this->withToken($this->token($this->adminA))->deleteJson($url)->assertForbidden();
    }

    public function test_admin_creates_custom_unit_but_supervisor_client_and_other_company_cannot_manage_it(): void
    {
        $response = $this->withToken($this->token($this->adminA))->postJson('/api/v1/measurement-units', $this->unitPayload())->assertCreated();
        $id = $response->json('data.id');
        $this->assertDatabaseHas('measurement_units', ['id' => $id, 'company_id' => $this->a->id, 'is_system' => false]);
        foreach ([$this->supervisor, $this->client] as $user) {
            $this->app['auth']->forgetGuards();
            $this->withToken($this->token($user))->postJson('/api/v1/measurement-units', $this->unitPayload('bx'))->assertForbidden();
        }
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->adminB))->getJson("/api/v1/measurement-units/{$id}")->assertNotFound();
    }

    public function test_unit_symbol_uniqueness_is_case_insensitive_and_reusable_after_soft_delete(): void
    {
        $token = $this->token($this->adminA);
        $first = $this->withToken($token)->postJson('/api/v1/measurement-units', $this->unitPayload('BOX'))->assertCreated();
        $this->withToken($token)->postJson('/api/v1/measurement-units', $this->unitPayload(' box '))->assertUnprocessable()->assertJsonValidationErrors('symbol');
        $this->withToken($token)->deleteJson('/api/v1/measurement-units/'.$first->json('data.id'))->assertOk();
        $this->withToken($token)->postJson('/api/v1/measurement-units', $this->unitPayload('box'))->assertCreated();
    }

    public function test_category_tenant_uniqueness_status_and_historical_reference_rules(): void
    {
        $a = $this->createCategory($this->adminA, ' Cement ');
        $this->withToken($this->token($this->adminA))->postJson('/api/v1/material-categories', ['name' => 'cement'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->createCategory($this->adminB, 'Cement');
        $this->withToken($this->token($this->adminB))->getJson("/api/v1/material-categories/{$a->id}")->assertNotFound();
        $material = $this->makeMaterial($a);
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->adminA))->deleteJson("/api/v1/material-categories/{$a->id}")->assertOk();
        $this->assertNotNull($material->fresh()->category);
        $this->assertDatabaseHas('materials', ['id' => $material->id]);
    }

    public function test_material_creation_derives_ownership_and_rejects_protected_or_cross_tenant_links(): void
    {
        $category = $this->createCategory($this->adminA, 'Steel');
        $payload = $this->materialPayload($category, $this->systemUnit);
        $response = $this->withToken($this->token($this->adminA))->postJson('/api/v1/materials', $payload)->assertCreated();
        $this->assertDatabaseHas('materials', ['id' => $response->json('data.id'), 'company_id' => $this->a->id, 'created_by' => $this->adminA->id]);
        $this->withToken($this->token($this->adminA))->postJson('/api/v1/materials', array_merge($payload, ['company_id' => $this->b->id, 'created_by' => $this->adminB->id]))->assertUnprocessable()->assertJsonValidationErrors(['company_id', 'created_by']);
        $foreignCategory = $this->createCategory($this->adminB, 'Steel');
        $foreignUnit = $this->customUnit($this->b, 'drum');
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->adminA))->postJson('/api/v1/materials', $this->materialPayload($foreignCategory, $this->systemUnit, 'X-1'))->assertUnprocessable()->assertJsonValidationErrors('material_category_id');
        $this->withToken($this->token($this->adminA))->postJson('/api/v1/materials', $this->materialPayload($category, $foreignUnit, 'X-2'))->assertUnprocessable()->assertJsonValidationErrors('measurement_unit_id');
    }

    public function test_inactive_category_or_unit_cannot_be_assigned_and_supervisor_is_view_only(): void
    {
        $category = $this->createCategory($this->adminA, 'Bricks');
        $unit = $this->customUnit($this->a, 'pallet');
        $category->update(['is_active' => false]);
        $this->withToken($this->token($this->adminA))->postJson('/api/v1/materials', $this->materialPayload($category, $unit))->assertUnprocessable()->assertJsonValidationErrors('material_category_id');
        $category->update(['is_active' => true]);
        $unit->update(['is_active' => false]);
        $this->withToken($this->token($this->adminA))->postJson('/api/v1/materials', $this->materialPayload($category, $unit))->assertUnprocessable()->assertJsonValidationErrors('measurement_unit_id');
        $unit->update(['is_active' => true]);
        $this->makeMaterial($category, $unit);
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->supervisor))->getJson('/api/v1/materials')->assertOk();
        $this->withToken($this->token($this->supervisor))->postJson('/api/v1/materials', $this->materialPayload($category, $unit, 'SUP-1'))->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->client))->getJson('/api/v1/materials')->assertForbidden();
    }

    public function test_material_uniqueness_filters_sort_pagination_status_and_soft_delete(): void
    {
        $category = $this->createCategory($this->adminA, 'Concrete');
        $other = $this->createCategory($this->adminA, 'Other');
        $unit = $this->customUnit($this->a, 'cube');
        $material = $this->makeMaterial($category, $unit, 'MAT-001', 'Alpha Mix');
        $this->makeMaterial($other, $this->systemUnit, 'MAT-002', 'Beta Cement');
        $this->withToken($this->token($this->adminA))->postJson('/api/v1/materials', $this->materialPayload($category, $unit, 'mat-001'))->assertUnprocessable()->assertJsonValidationErrors('code');
        $foreignCategory = $this->createCategory($this->adminB, 'Concrete');
        $this->withToken($this->token($this->adminB))->postJson('/api/v1/materials', $this->materialPayload($foreignCategory, $this->systemUnit, 'MAT-001'))->assertCreated();
        $token = $this->token($this->adminA);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson("/api/v1/materials?search=Alpha&material_category_id={$category->id}&measurement_unit_id={$unit->id}&is_active=1&per_page=1&sort=name&direction=asc")->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $material->id);
        $this->withToken($token)->getJson('/api/v1/materials?sort=code')->assertUnprocessable();
        $this->withToken($token)->patchJson("/api/v1/materials/{$material->id}/status", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
        $this->withToken($token)->patchJson("/api/v1/materials/{$material->id}/status", ['is_active' => true])->assertOk()->assertJsonPath('data.is_active', true);
        $this->withToken($token)->deleteJson("/api/v1/materials/{$material->id}")->assertOk();
        $this->assertSoftDeleted('materials', ['id' => $material->id]);
        $this->withToken($token)->getJson("/api/v1/materials/{$material->id}")->assertNotFound();
    }

    public function test_cross_company_material_access_and_activity_logs_are_safe(): void
    {
        $category = $this->createCategory($this->adminA, 'Paint');
        $material = $this->makeMaterial($category);
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->adminB))->getJson("/api/v1/materials/{$material->id}")->assertNotFound();
        $token = $this->token($this->adminA);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->patchJson("/api/v1/materials/{$material->id}", ['brand' => 'Safe Brand'])->assertOk();
        $this->withToken($token)->deleteJson("/api/v1/materials/{$material->id}")->assertOk();
        $events = DB::table('activity_log')->whereIn('event', ['material.updated', 'material.deleted'])->pluck('event')->all();
        $this->assertContains('material.updated', $events);
        $this->assertContains('material.deleted', $events);
        $properties = DB::table('activity_log')->pluck('properties')->implode(' ');
        $this->assertStringNotContainsString('password', strtolower($properties));
        $this->assertStringNotContainsString('token', strtolower($properties));
    }

    private function user(Company $company, string $role): User
    {
        $u = User::factory()->for($company)->create(['status' => 'Active']);
        $this->grantRole($u, $role);

        return $u;
    }

    private function token(User $user): string
    {
        return $user->createToken('catalog-test')->plainTextToken;
    }

    private function unitPayload(string $symbol = 'box'): array
    {
        return ['name' => 'Box', 'symbol' => $symbol, 'unit_type' => 'count', 'decimal_precision' => 0];
    }

    private function createCategory(User $admin, string $name): MaterialCategory
    {
        $this->app['auth']->forgetGuards();
        $response = $this->withToken($this->token($admin))->postJson('/api/v1/material-categories', ['name' => $name])->assertCreated();

        return MaterialCategory::findOrFail($response->json('data.id'));
    }

    private function customUnit(Company $company, string $symbol): MeasurementUnit
    {
        return MeasurementUnit::forceCreate(['company_id' => $company->id, 'name' => ucfirst($symbol), 'symbol' => $symbol, 'unit_type' => 'count', 'decimal_precision' => 0, 'is_system' => false, 'is_active' => true]);
    }

    private function materialPayload(MaterialCategory $category, MeasurementUnit $unit, string $code = 'MAT-001'): array
    {
        return ['name' => 'Test Material', 'code' => $code, 'material_category_id' => $category->id, 'measurement_unit_id' => $unit->id, 'description' => 'Test'];
    }

    private function makeMaterial(MaterialCategory $category, ?MeasurementUnit $unit = null, string $code = 'MAT-001', string $name = 'Test Material'): Material
    {
        return Material::forceCreate(['company_id' => $category->company_id, 'material_category_id' => $category->id, 'measurement_unit_id' => ($unit ?? $this->systemUnit)->id, 'name' => $name, 'code' => $code, 'is_active' => true, 'created_by' => $category->created_by]);
    }
}
