<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateProjectTeamRequest;
use App\Http\Resources\Api\V1\ProjectTeamMemberResource;
use App\Models\Project;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectTeamController extends Controller
{
    public function show(Request $request, TenantContext $tenant, int $id): JsonResponse
    {
        $project = $this->project($request, $tenant, $id);
        if (! $project) {
            return $this->notFound();
        }
        if (! $request->user()->can('projects.assign') || ! $request->user()->can('manageTeam', $project)) {
            return $this->forbidden();
        }
        $members = $project->members()->with('roles:id,name')->orderBy('users.name')->get();

        return response()->json(['data' => ProjectTeamMemberResource::collection($members)]);
    }

    public function update(UpdateProjectTeamRequest $request, TenantContext $tenant, int $id): JsonResponse
    {
        $project = $this->project($request, $tenant, $id);
        if (! $project) {
            return $this->notFound();
        }
        if (! $request->user()->can('projects.assign') || ! $request->user()->can('manageTeam', $project)) {
            return $this->forbidden();
        }
        $requested = collect($request->validated('members'));
        $users = User::query()->whereIn('id', $requested->pluck('user_id'))->get()->keyBy('id');
        $errors = [];
        foreach ($requested as $index => $assignment) {
            $user = $users->get($assignment['user_id']);
            if (! $user || (int) $user->company_id !== $tenant->companyId()) {
                $errors["members.{$index}.user_id"][] = 'The selected user is invalid for this company.';
            } elseif (! $user->isActive()) {
                $errors["members.{$index}.user_id"][] = 'Only active users can be assigned.';
            } elseif (! $this->eligible($user, $assignment['assignment_type'])) {
                $errors["members.{$index}.assignment_type"][] = 'The user is not eligible for this assignment type.';
            }
        }
        if ($errors) {
            return response()->json(['message' => 'The given data was invalid.', 'errors' => $errors], 422);
        }

        $before = $project->members()->get()->mapWithKeys(fn ($user) => [(int) $user->id => $user->pivot->assignment_type]);
        $now = now();
        $sync = $requested->mapWithKeys(fn ($assignment) => [(int) $assignment['user_id'] => [
            'company_id' => $tenant->companyId(), 'assignment_type' => $assignment['assignment_type'],
            'assigned_by' => $request->user()->id, 'assigned_at' => $now,
        ]])->all();
        DB::transaction(function () use ($project, $sync, $request, $before, $requested): void {
            $project->members()->sync($sync);
            $after = $requested->mapWithKeys(fn ($item) => [(int) $item['user_id'] => $item['assignment_type']]);
            foreach ($after->keys()->diff($before->keys()) as $userId) {
                $this->memberLog($request, $project, 'project.member_assigned', $userId, $after[$userId]);
            }
            foreach ($before->keys()->diff($after->keys()) as $userId) {
                $this->memberLog($request, $project, 'project.member_removed', $userId, $before[$userId]);
            }
            foreach ($after->keys()->intersect($before->keys()) as $userId) {
                if ($after[$userId] !== $before[$userId]) {
                    $this->memberLog($request, $project, 'project.member_removed', $userId, $before[$userId]);
                    $this->memberLog($request, $project, 'project.member_assigned', $userId, $after[$userId]);
                }
            }
            activity('projects')->causedBy($request->user())->performedOn($project)->event('project.team_updated')
                ->withProperties(['company_id' => $project->company_id, 'project_id' => $project->id,
                    'before' => $before, 'after' => $after])->log('project.team_updated');
        });

        return response()->json(['data' => ProjectTeamMemberResource::collection(
            $project->members()->with('roles:id,name')->orderBy('users.name')->get(),
        ), 'message' => 'Project team updated successfully.']);
    }

    private function project(Request $request, TenantContext $tenant, int $id): ?Project
    {
        $project = Project::query()->forCompany($tenant->companyId())->find($id);

        return $project && $request->user()->can('view', $project) ? $project : null;
    }

    private function eligible(User $user, string $type): bool
    {
        return match ($type) {
            'supervisor' => $user->hasRole('site_supervisor'),
            'client' => $user->hasRole('client'),
            'member' => $user->hasAnyRole(['company_admin', 'site_supervisor']),
            default => false,
        };
    }

    private function memberLog(Request $request, Project $project, string $event, int $userId, string $type): void
    {
        activity('projects')->causedBy($request->user())->performedOn($project)->event($event)
            ->withProperties(['company_id' => $project->company_id, 'project_id' => $project->id,
                'target_user_id' => $userId, 'assignment_type' => $type])->log($event);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json(['message' => 'You do not have permission to perform this action.'], 403);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => 'Project not found.'], 404);
    }
}
