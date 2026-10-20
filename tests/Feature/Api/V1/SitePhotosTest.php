<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\DailySiteReport;
use App\Models\Project;
use App\Models\SitePhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SitePhotosTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    private User $admin;

    private User $supervisor;

    private User $otherSupervisor;

    private User $client;

    private User $foreignAdmin;

    private Project $project;

    private Project $foreignProject;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->company = Company::factory()->create();
        $this->otherCompany = Company::factory()->create();
        $this->admin = $this->user($this->company, 'company_admin');
        $this->supervisor = $this->user($this->company, 'site_supervisor');
        $this->otherSupervisor = $this->user($this->company, 'site_supervisor');
        $this->client = $this->user($this->company, 'client');
        $this->foreignAdmin = $this->user($this->otherCompany, 'company_admin');
        $this->project = $this->makeProject($this->company, $this->admin, 'PHOTO-A');
        $this->foreignProject = $this->makeProject($this->otherCompany, $this->foreignAdmin, 'PHOTO-B');
        $this->attach($this->project, $this->supervisor, 'supervisor');
        $this->attach($this->project, $this->client, 'client');
    }

    public function test_admin_and_assigned_supervisor_upload_private_pending_randomly_named_photos(): void
    {
        foreach ([$this->admin, $this->supervisor] as $user) {
            $response = $this->withToken($this->token($user))->post($this->url(), $this->payload('unsafe/../../shell.php.jpg'))
                ->assertCreated()->assertJsonPath('data.approval_status', 'pending')->assertJsonPath('data.is_client_visible', false);
            $photo = SitePhoto::findOrFail($response->json('data.id'));
            $this->assertSame($this->company->id, $photo->company_id);
            $this->assertSame($this->project->id, $photo->project_id);
            $this->assertSame($user->id, $photo->uploaded_by);
            $this->assertNotSame($photo->original_filename, $photo->stored_filename);
            $this->assertStringStartsWith("companies/{$this->company->id}/projects/{$this->project->id}/site-photos/", $photo->storage_path);
            Storage::disk('local')->assertExists($photo->storage_path);
            $this->app['auth']->forgetGuards();
        }
    }

    public function test_unassigned_supervisor_client_and_cross_company_user_cannot_upload(): void
    {
        $this->withToken($this->token($this->otherSupervisor))->post($this->url(), $this->payload())->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->client))->post($this->url(), $this->payload())->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->admin))->post("/api/v1/projects/{$this->foreignProject->id}/photos", $this->payload())->assertNotFound();
    }

    public function test_protected_fields_and_invalid_files_are_rejected_without_records(): void
    {
        $protected = array_merge($this->payload(), ['company_id' => $this->otherCompany->id, 'approval_status' => 'approved', 'is_client_visible' => true, 'storage_path' => 'public/x']);
        $this->withToken($this->token($this->admin))->post($this->url(), $protected)->assertUnprocessable()->assertJsonValidationErrors(['company_id', 'approval_status', 'is_client_visible', 'storage_path']);
        $this->withToken($this->token($this->admin))->post($this->url(), array_merge($this->payload(), ['photo' => UploadedFile::fake()->create('bad.svg', 10, 'image/svg+xml')]))->assertUnprocessable();
        $this->withToken($this->token($this->admin))->post($this->url(), array_merge($this->payload(), ['photo' => UploadedFile::fake()->create('bad.pdf', 10, 'application/pdf')]))->assertUnprocessable();
        $this->withToken($this->token($this->admin))->post($this->url(), array_merge($this->payload(), ['photo' => $this->imageFile('large.png', 10241 * 1024)]))->assertUnprocessable();
        $this->assertDatabaseCount('site_photos', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_batch_is_limited_validated_and_successful_upload_is_atomic(): void
    {
        $payload = ['photos' => [$this->imageFile('a.png'), $this->imageFile('b.png')], 'photo_date' => '2026-10-20', 'time_category' => 'afternoon'];
        $this->withToken($this->token($this->admin))->post($this->url().'/batch', $payload)->assertCreated()->assertJsonCount(2, 'data');
        $this->assertDatabaseCount('site_photos', 2);
        $this->assertCount(2, Storage::disk('local')->allFiles());
        $tooMany = collect(range(1, 11))->map(fn ($i) => $this->imageFile("x{$i}.png"))->all();
        $this->withToken($this->token($this->admin))->post($this->url().'/batch', ['photos' => $tooMany, 'photo_date' => '2026-10-20', 'time_category' => 'morning'])->assertUnprocessable();
        $this->withToken($this->token($this->admin))->post($this->url().'/batch', ['photos' => [$this->imageFile('ok.png'), UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml')], 'photo_date' => '2026-10-20', 'time_category' => 'morning'])->assertUnprocessable();
        $this->assertDatabaseCount('site_photos', 2);
        $this->assertCount(2, Storage::disk('local')->allFiles());
    }

    public function test_gallery_details_and_files_enforce_client_visibility_project_and_safe_output(): void
    {
        $pending = $this->photo();
        $published = $this->photo(['approval_status' => 'approved', 'is_client_visible' => true]);
        $this->withToken($this->token($this->client))->getJson($this->url())->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $published->id);
        $this->withToken($this->token($this->client))->getJson($this->url()."/{$pending->id}")->assertNotFound();
        $this->withToken($this->token($this->client))->getJson($this->url()."/{$published->id}")->assertOk()->assertJsonMissingPath('data.storage_path')->assertJsonMissingPath('data.review_notes');
        $fileResponse = $this->withToken($this->token($this->client))->get($this->url()."/{$published->id}/file")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('private', $fileResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $fileResponse->headers->get('Cache-Control'));
        $this->withToken($this->token($this->client))->get($this->url()."/{$pending->id}/file")->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', '')->getJson($this->url()."/{$published->id}/file")->assertUnauthorized();
        $this->withToken($this->token($this->admin))->getJson("/api/v1/projects/{$this->foreignProject->id}/photos/{$published->id}")->assertNotFound();
    }

    public function test_metadata_ownership_review_visibility_and_soft_delete_workflow(): void
    {
        $own = $this->photo(['uploaded_by' => $this->supervisor->id]);
        $other = $this->photo(['uploaded_by' => $this->admin->id]);
        $this->withToken($this->token($this->supervisor))->patchJson($this->url()."/{$own->id}", ['caption' => 'Changed'])->assertOk();
        $this->withToken($this->token($this->supervisor))->patchJson($this->url()."/{$other->id}", ['caption' => 'No'])->assertForbidden();
        $this->withToken($this->token($this->supervisor))->postJson($this->url()."/{$own->id}/review", ['action' => 'approve'])->assertForbidden();
        $this->app['auth']->forgetGuards();
        $token = $this->token($this->admin);
        $this->withToken($token)->postJson($this->url()."/{$own->id}/review", ['action' => 'approve'])->assertOk()->assertJsonPath('data.is_client_visible', false);
        $this->withToken($token)->patchJson($this->url()."/{$own->id}/visibility", ['is_client_visible' => true])->assertOk();
        $this->withToken($token)->patchJson($this->url()."/{$own->id}/visibility", ['is_client_visible' => false])->assertOk();
        $this->withToken($token)->patchJson($this->url()."/{$other->id}/visibility", ['is_client_visible' => true])->assertUnprocessable();
        $this->withToken($token)->deleteJson($this->url()."/{$own->id}")->assertOk();
        $this->assertSoftDeleted('site_photos', ['id' => $own->id]);
        Storage::disk('local')->assertExists($own->storage_path);
        $this->withToken($token)->get($this->url()."/{$own->id}/file")->assertNotFound();
    }

    public function test_rejection_requires_notes_and_daily_report_links_are_tenant_project_scoped(): void
    {
        $photo = $this->photo();
        $token = $this->token($this->admin);
        $this->withToken($token)->postJson($this->url()."/{$photo->id}/review", ['action' => 'reject'])->assertUnprocessable()->assertJsonValidationErrors('review_notes');
        $this->withToken($token)->postJson($this->url()."/{$photo->id}/review", ['action' => 'reject', 'review_notes' => 'Blurred'])->assertOk()->assertJsonPath('data.approval_status', 'rejected');
        $foreignReport = DailySiteReport::factory()->create(['company_id' => $this->otherCompany->id, 'project_id' => $this->foreignProject->id, 'created_by' => $this->foreignAdmin->id]);
        $this->withToken($token)->post($this->url(), array_merge($this->payload(), ['daily_site_report_id' => $foreignReport->id]))->assertUnprocessable()->assertJsonValidationErrors('daily_site_report_id');
    }

    public function test_daily_report_photo_listing_requires_visible_report_for_client_and_logs_are_safe(): void
    {
        $report = DailySiteReport::factory()->create(['company_id' => $this->company->id, 'project_id' => $this->project->id, 'created_by' => $this->admin->id, 'status' => 'approved', 'is_client_visible' => false]);
        $photo = $this->photo(['daily_site_report_id' => $report->id, 'approval_status' => 'approved', 'is_client_visible' => true]);
        $url = "/api/v1/projects/{$this->project->id}/daily-reports/{$report->id}/photos";
        $this->withToken($this->token($this->client))->getJson($url)->assertNotFound();
        $report->forceFill(['is_client_visible' => true])->save();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->client))->getJson($url)->assertOk()->assertJsonPath('data.0.id', $photo->id);
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->admin))->patchJson($this->url()."/{$photo->id}", ['caption' => 'Logged'])->assertOk();
        $log = DB::table('activity_log')->latest('id')->first();
        $this->assertSame('site_photo.updated', $log->event);
        $this->assertStringNotContainsString('storage_path', strtolower($log->properties));
        $this->assertStringNotContainsString('token', strtolower($log->properties));
    }

    private function user(Company $company, string $role): User
    {
        $u = User::factory()->for($company)->create(['status' => 'Active']);
        $this->grantRole($u, $role);

        return $u;
    }

    private function makeProject(Company $c, User $u, string $code): Project
    {
        return Project::factory()->for($c)->create(['created_by' => $u->id, 'currency' => $c->currency, 'code' => $code]);
    }

    private function attach(Project $p, User $u, string $type): void
    {
        $p->members()->attach($u->id, ['company_id' => $p->company_id, 'assignment_type' => $type, 'assigned_by' => $p->created_by, 'assigned_at' => now()]);
    }

    private function token(User $u): string
    {
        return $u->createToken('photo-test')->plainTextToken;
    }

    private function url(): string
    {
        return "/api/v1/projects/{$this->project->id}/photos";
    }

    private function payload(string $name = 'photo.png'): array
    {
        return ['photo' => $this->imageFile(basename($name)), 'caption' => 'Foundation', 'photo_date' => '2026-10-20', 'time_category' => 'morning'];
    }

    private function photo(array $attributes = []): SitePhoto
    {
        $file = $this->imageFile('stored.png');
        $name = uniqid().'.png';
        $path = "companies/{$this->company->id}/projects/{$this->project->id}/site-photos/{$name}";
        Storage::disk('local')->putFileAs(dirname($path), $file, $name);

        return SitePhoto::forceCreate(array_merge(['company_id' => $this->company->id, 'project_id' => $this->project->id, 'uploaded_by' => $this->admin->id, 'photo_date' => '2026-10-20', 'time_category' => 'morning', 'original_filename' => 'stored.png', 'stored_filename' => $name, 'storage_disk' => 'local', 'storage_path' => $path, 'mime_type' => 'image/png', 'file_size' => 68, 'approval_status' => 'pending', 'is_client_visible' => false], $attributes));
    }

    private function imageFile(string $name, ?int $bytes = null): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        if ($bytes !== null && $bytes > strlen($png)) {
            $png .= str_repeat("\0", $bytes - strlen($png));
        }

        return UploadedFile::fake()->createWithContent($name, $png);
    }
}
