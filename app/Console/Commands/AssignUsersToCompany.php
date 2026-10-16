<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignUsersToCompany extends Command
{
    protected $signature = 'company:assign-users
        {company : Target company ID or slug}
        {user_ids* : Explicit user IDs to assign}
        {--commit : Persist the assignment; without this option the command is a dry run}';

    protected $description = 'Safely assign explicitly selected unassigned users to a company';

    public function handle(): int
    {
        $identifier = (string) $this->argument('company');
        $companyQuery = Company::query()->where('slug', $identifier);

        if (ctype_digit($identifier)) {
            $companyQuery->orWhere('id', (int) $identifier);
        }

        $company = $companyQuery->first();

        if (! $company) {
            $this->error('The target company was not found.');

            return self::FAILURE;
        }

        $userIds = collect($this->argument('user_ids'))
            ->map(fn ($id) => filter_var($id, FILTER_VALIDATE_INT))
            ->filter(fn ($id) => $id !== false && $id > 0)
            ->unique()
            ->values();

        if ($userIds->count() !== count(array_unique($this->argument('user_ids')))) {
            $this->error('Every user ID must be a unique positive integer.');

            return self::FAILURE;
        }

        $users = User::query()->whereKey($userIds)->get();

        if ($users->count() !== $userIds->count()) {
            $missing = $userIds->diff($users->modelKeys())->implode(', ');
            $this->error("User IDs not found: {$missing}");

            return self::FAILURE;
        }

        $conflicts = $users
            ->filter(fn (User $user) => $user->company_id !== null && $user->company_id !== $company->id)
            ->modelKeys();

        if ($conflicts !== []) {
            $this->error('Users already assigned to another company: '.implode(', ', $conflicts));

            return self::FAILURE;
        }

        $unassignedIds = $users->whereNull('company_id')->modelKeys();

        $this->table(
            ['Company ID', 'Company', 'Users to assign', 'Already assigned'],
            [[
                $company->id,
                $company->name,
                count($unassignedIds),
                $users->count() - count($unassignedIds),
            ]],
        );

        if (! $this->option('commit')) {
            $this->warn('Dry run only. Re-run with --commit after verifying the company and user IDs.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($company, $unassignedIds): void {
            User::query()
                ->whereKey($unassignedIds)
                ->whereNull('company_id')
                ->update(['company_id' => $company->id]);
        });

        $this->info('Company assignment completed successfully.');

        return self::SUCCESS;
    }
}
