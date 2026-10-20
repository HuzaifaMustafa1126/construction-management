<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Models\DailySiteReport;
use App\Models\SitePhoto;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Validation\Rule;

trait SitePhotoRules
{
    protected function metadataRules(): array
    {
        return [
            'caption' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'photo_date' => ['required', 'date'],
            'time_category' => ['required', Rule::in(SitePhoto::TIME_CATEGORIES)],
            'daily_site_report_id' => ['nullable', 'integer', $this->validReport()],
        ];
    }

    protected function protectedRules(): array
    {
        return collect(['company_id', 'project_id', 'uploaded_by', 'approval_status', 'is_client_visible',
            'reviewed_by', 'reviewed_at', 'review_notes', 'storage_disk', 'storage_path', 'stored_filename',
            'original_filename', 'mime_type', 'file_size'])->mapWithKeys(fn ($key) => [$key => ['prohibited']])->all();
    }

    protected function imageRules(): array
    {
        return ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'];
    }

    private function validReport(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null) {
                return;
            }
            $valid = DailySiteReport::query()->forCompany(app(TenantContext::class)->companyId())
                ->where('project_id', $this->route('id'))->whereKey($value)->exists();
            if (! $valid) {
                $fail('The selected daily site report is invalid for this project.');
            }
        };
    }
}
