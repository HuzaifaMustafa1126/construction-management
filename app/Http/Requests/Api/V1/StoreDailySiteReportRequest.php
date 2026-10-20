<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\DailySiteReportRules;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class StoreDailySiteReportRequest extends FormRequest
{
    use DailySiteReportRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = array_merge($this->contentRules(), $this->protectedRules());
        $rules['report_date'][] = $this->uniqueDate();

        return $rules;
    }

    private function uniqueDate(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (DB::table('daily_site_reports')->where('company_id', app(TenantContext::class)->companyId())
                ->where('project_id', $this->route('id'))->whereDate('report_date', $value)
                ->whereNull('deleted_at')->exists()) {
                $fail('An active daily report already exists for this project and date.');
            }
        };
    }
}
