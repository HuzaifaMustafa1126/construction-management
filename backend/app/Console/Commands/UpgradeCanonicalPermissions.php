<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Authorization\PermissionCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class UpgradeCanonicalPermissions extends Command
{
    protected $signature = 'permissions:upgrade-canonical {--apply : Add canonical grants inside a transaction}';

    protected $description = 'Preview or apply exact legacy-to-canonical permission grant upgrades';

    public function handle(): int
    {
        $rows = [];
        $safe = true;
        $missingDefinitions = [];
        $unsupportedGuards = [];
        $unsupportedModelGrants = 0;
        $invalidAssignments = 0;

        foreach (PermissionCatalog::LEGACY_TO_CANONICAL as $legacy => $canonical) {
            $legacyDefinitions = DB::table('permissions')->where('name', $legacy)->get(['id', 'guard_name']);
            $guards = $legacyDefinitions->pluck('guard_name')->unique()->sort()->values();

            if ($guards->isEmpty()) {
                $rows[] = [$legacy, $canonical, 0, 0, '-', 'none', 'yes'];

                continue;
            }

            foreach ($guards as $guard) {
                $legacyId = (int) $legacyDefinitions->firstWhere('guard_name', $guard)->id;
                $canonicalDefinition = DB::table('permissions')->where('name', $canonical)->where('guard_name', $guard)->first(['id']);
                $roleQuery = DB::table('role_has_permissions as grant')
                    ->join('roles', 'roles.id', '=', 'grant.role_id')
                    ->where('grant.permission_id', $legacyId)
                    ->where('roles.guard_name', $guard);
                $directQuery = DB::table('model_has_permissions')
                    ->where('permission_id', $legacyId)
                    ->where('model_type', User::class);
                $unsupported = DB::table('model_has_permissions')
                    ->where('permission_id', $legacyId)
                    ->where('model_type', '!=', User::class)
                    ->count();
                $invalidRoleGuards = DB::table('role_has_permissions as grant')
                    ->join('roles', 'roles.id', '=', 'grant.role_id')
                    ->where('grant.permission_id', $legacyId)
                    ->where('roles.guard_name', '!=', $guard)
                    ->count();
                $invalidRoleTeams = DB::table('role_has_permissions as grant')
                    ->join('roles', 'roles.id', '=', 'grant.role_id')
                    ->leftJoin('companies', 'companies.id', '=', 'roles.company_id')
                    ->where('grant.permission_id', $legacyId)
                    ->where(function ($query): void {
                        $query->whereNull('roles.company_id')->orWhereNull('companies.id');
                    })
                    ->count();
                $invalidDirectTeams = DB::table('model_has_permissions as grant')
                    ->leftJoin('users', function ($join): void {
                        $join->on('users.id', '=', 'grant.model_id')->where('grant.model_type', User::class);
                    })
                    ->where('grant.permission_id', $legacyId)
                    ->where(function ($query): void {
                        $query->whereNull('users.id')
                            ->orWhereNull('users.company_id')
                            ->orWhereColumn('users.company_id', '!=', 'grant.company_id');
                    })
                    ->count();
                $legacyRoleIds = (clone $roleQuery)->pluck('roles.id');
                $legacyDirectGrants = (clone $directQuery)->get(['model_id', 'company_id']);
                $affectedRoles = $canonicalDefinition
                    ? $legacyRoleIds->diff(DB::table('role_has_permissions')
                        ->where('permission_id', $canonicalDefinition->id)
                        ->whereIn('role_id', $legacyRoleIds)
                        ->pluck('role_id'))->count()
                    : $legacyRoleIds->count();
                $canonicalDirectKeys = $canonicalDefinition
                    ? DB::table('model_has_permissions')
                        ->where('permission_id', $canonicalDefinition->id)
                        ->where('model_type', User::class)
                        ->get(['model_id', 'company_id'])
                        ->mapWithKeys(fn ($grant) => [$grant->model_id.':'.$grant->company_id => true])
                    : collect();
                $affectedDirectUsers = $legacyDirectGrants
                    ->reject(fn ($grant) => $canonicalDirectKeys->has($grant->model_id.':'.$grant->company_id))
                    ->count();
                $companies = (clone $roleQuery)->pluck('roles.company_id')
                    ->merge((clone $directQuery)->pluck('company_id'))
                    ->filter()->unique()->sort()->implode(',');
                $mappingSafe = $canonicalDefinition !== null
                    && $guard === 'web'
                    && $unsupported === 0
                    && $invalidRoleGuards === 0
                    && $invalidRoleTeams === 0
                    && $invalidDirectTeams === 0;
                $safe = $safe && $mappingSafe;
                if (! $canonicalDefinition) {
                    $missingDefinitions[] = $canonical.'@'.$guard;
                }
                if ($guard !== 'web') {
                    $unsupportedGuards[] = $guard;
                }
                $unsupportedModelGrants += $unsupported;
                $invalidAssignments += $invalidRoleGuards + $invalidRoleTeams + $invalidDirectTeams;

                $rows[] = [
                    $legacy,
                    $canonical,
                    $affectedRoles,
                    $affectedDirectUsers,
                    $companies !== '' ? $companies : '-',
                    $canonicalDefinition ? 'present' : 'MISSING',
                    $mappingSafe ? 'yes' : 'NO',
                ];
            }
        }

        $this->table(
            ['Legacy', 'Canonical', 'Roles', 'Direct users', 'Companies', 'Canonical definition', 'Safe'],
            $rows,
        );
        $this->line('Mode: '.($this->option('apply') ? 'APPLY requested' : 'PREVIEW only'));
        $this->line('Missing canonical definitions: '.($missingDefinitions === [] ? 'none' : implode(', ', array_unique($missingDefinitions))));
        $this->line('Unsupported guards: '.($unsupportedGuards === [] ? 'none' : implode(', ', array_unique($unsupportedGuards))));
        $this->line('Unsupported direct-grant model rows: '.$unsupportedModelGrants);
        $this->line('Invalid or ambiguous assignments: '.$invalidAssignments);
        $this->line('Overall safe to apply: '.($safe ? 'yes' : 'NO'));

        if (! $this->option('apply')) {
            $this->info('No database grants were changed. Re-run with --apply only after reviewing this preview.');

            return self::SUCCESS;
        }

        if (! $safe) {
            $this->error('Upgrade blocked: resolve missing definitions or invalid/unsupported assignments first.');

            return self::FAILURE;
        }

        DB::transaction(function (): void {
            foreach (PermissionCatalog::LEGACY_TO_CANONICAL as $legacy => $canonical) {
                $legacyDefinitions = DB::table('permissions')->where('name', $legacy)->get(['id', 'guard_name']);
                foreach ($legacyDefinitions as $legacyDefinition) {
                    $canonicalId = DB::table('permissions')
                        ->where('name', $canonical)
                        ->where('guard_name', $legacyDefinition->guard_name)
                        ->value('id');

                    $roleGrants = DB::table('role_has_permissions')
                        ->where('permission_id', $legacyDefinition->id)
                        ->get(['role_id'])
                        ->map(fn ($grant) => ['permission_id' => $canonicalId, 'role_id' => $grant->role_id])
                        ->all();
                    if ($roleGrants !== []) {
                        DB::table('role_has_permissions')->insertOrIgnore($roleGrants);
                    }

                    $directGrants = DB::table('model_has_permissions')
                        ->where('permission_id', $legacyDefinition->id)
                        ->where('model_type', User::class)
                        ->get(['model_type', 'model_id', 'company_id'])
                        ->map(fn ($grant) => [
                            'permission_id' => $canonicalId,
                            'model_type' => $grant->model_type,
                            'model_id' => $grant->model_id,
                            'company_id' => $grant->company_id,
                        ])->all();
                    if ($directGrants !== []) {
                        DB::table('model_has_permissions')->insertOrIgnore($directGrants);
                    }
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->info('Canonical grants added. Legacy definitions and grants were preserved.');

        return self::SUCCESS;
    }
}
