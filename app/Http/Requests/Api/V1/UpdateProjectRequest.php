<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Project;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $project = Project::query()->forCompany(app(TenantContext::class)->companyId())->find($this->route('id'));
        $start = $this->input('start_date', $project?->start_date?->format('Y-m-d'));

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['sometimes', 'required', 'string', 'max:75', 'regex:/^[A-Za-z0-9_-]+$/', $this->uniqueCode($project)],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'], 'address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'], 'state' => ['sometimes', 'nullable', 'string', 'max:100'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'], 'postal_code' => ['sometimes', 'nullable', 'string', 'max:30'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'expected_end_date' => ['sometimes', 'nullable', $start ? Rule::date()->afterOrEqual($start) : Rule::date()],
            'actual_end_date' => ['sometimes', 'nullable', $start ? Rule::date()->afterOrEqual($start) : Rule::date()],
            'budget' => ['sometimes', 'numeric', 'min:0', 'max:9999999999999.99'],
            'company_id' => ['prohibited'], 'currency' => ['prohibited'], 'created_by' => ['prohibited'],
            'updated_by' => ['prohibited'], 'status' => ['prohibited'], 'progress_percentage' => ['prohibited'],
            'members' => ['prohibited'], 'team' => ['prohibited'],
        ];
    }

    private function uniqueCode(?Project $project): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($project): void {
            if (! $project) {
                return;
            }
            if (DB::table('projects')->where('company_id', $project->company_id)->whereNull('deleted_at')
                ->where('id', '!=', $project->id)->whereRaw('lower(code) = ?', [mb_strtolower((string) $value)])->exists()) {
                $fail('The project code has already been taken.');
            }
        };
    }
}
