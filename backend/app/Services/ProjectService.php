<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    public function create(array $attributes, int $companyId, string $currency, User $actor): Project
    {
        return DB::transaction(function () use ($attributes, $companyId, $currency, $actor): Project {
            if (blank($attributes['code'] ?? null)) {
                $attributes['code'] = $this->nextCode($companyId);
            }

            $project = new Project($attributes);
            $project->company_id = $companyId;
            $project->currency = strtoupper($currency ?: 'PKR');
            $project->created_by = $actor->id;
            $project->status = 'planning';
            $project->progress_percentage = 0;
            $project->save();

            return $project;
        }, 3);
    }

    private function nextCode(int $companyId): string
    {
        DB::table('project_code_counters')->insertOrIgnore([
            'company_id' => $companyId, 'next_number' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $counter = DB::table('project_code_counters')->where('company_id', $companyId)->lockForUpdate()->first();
        $number = (int) $counter->next_number;

        do {
            $code = 'PRJ-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            $number++;
        } while (Project::withTrashed()->forCompany($companyId)->whereRaw('lower(code) = ?', [mb_strtolower($code)])->exists());

        DB::table('project_code_counters')->where('company_id', $companyId)->update([
            'next_number' => $number, 'updated_at' => now(),
        ]);

        return $code;
    }
}
