<?php

namespace App\Services;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ClientService
{
    private const CONTACT_FIELDS = ['name', 'company_name', 'email', 'phone', 'address', 'city', 'country', 'notes'];

    public function accountOptions(User $actor, int $companyId): array
    {
        $schemaReady = Schema::hasColumn('clients', 'user_id');
        $role = $this->clientRole($companyId);
        $grantable = $role && ! $role->permissions->contains(fn ($permission) => ! $actor->can($permission->name));
        $base = (int) $actor->company_id === $companyId && $actor->can('users.create') && $actor->can('roles.assign');
        $reason = ! $schemaReady ? 'Linked client accounts require the pending clients.user_id migration.'
            : (! $role ? 'The company client role is unavailable.'
                : (! $grantable ? 'You cannot assign the company client role because it grants permissions you do not hold.'
                    : (! $base ? 'User creation and role assignment permissions are required.' : null)));

        return [
            'schema_ready' => $schemaReady,
            'can_create' => $schemaReady && $base && $grantable && $actor->can('clients.create'),
            'can_link' => $schemaReady && $base && $grantable && $actor->can('clients.update'),
            'reason' => $reason,
        ];
    }

    public function authorizeProvisioning(User $actor, int $companyId, string $permission): Role
    {
        abort_unless((int) $actor->company_id === $companyId && (int) getPermissionsTeamId() === $companyId
            && $actor->can($permission) && $actor->can('users.create') && $actor->can('roles.assign'), 403,
            'Client account creation requires the corresponding client permission, users.create and roles.assign.');
        $role = $this->clientRole($companyId, DB::transactionLevel() > 0);
        if (! $role) {
            throw ValidationException::withMessages(['account' => ['The company client role is unavailable.']]);
        }
        abort_if($role->permissions->contains(fn ($permission) => ! $actor->can($permission->name)), 403,
            'You cannot assign the company client role because it grants permissions you do not hold.');
        abort_unless(Schema::hasColumn('clients', 'user_id'), 503,
            'Linked client accounts require the pending clients.user_id migration.');

        return $role;
    }

    public function create(array $attributes, int $companyId, User $actor): Client
    {
        try {
            return DB::transaction(function () use ($attributes, $companyId, $actor): Client {
                $role = $this->authorizeProvisioning($actor, $companyId, 'clients.create');
                $user = $this->createUser($attributes, $companyId, $actor, $role);
                $contact = Arr::only($attributes, self::CONTACT_FIELDS);
                $client = new Client($contact);
                $client->company_id = $companyId;
                $client->user_id = $user->id;
                $client->save();
                $this->log($actor, $client, 'client.created', ['after' => $client->only(self::CONTACT_FIELDS), 'user_id' => $user->id]);

                return $client;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $this->duplicateEmail($attributes['email'], $exception);
        }
    }

    public function createLoginAccount(Client $client, array $attributes, int $companyId, User $actor): Client
    {
        try {
            return DB::transaction(function () use ($client, $attributes, $companyId, $actor): Client {
                $role = $this->authorizeProvisioning($actor, $companyId, 'clients.update');
                $target = Client::query()->forCompany($companyId)->whereKey($client->id)->lockForUpdate()->firstOrFail();
                if ($target->user_id !== null) {
                    throw new ConflictHttpException('This client already has a linked login account.');
                }
                $user = $this->createUser([...$attributes, 'phone' => $target->phone], $companyId, $actor, $role);
                $target->user_id = $user->id;
                $target->save();
                $this->log($actor, $target, 'client.account_created', ['user_id' => $user->id]);

                return $target;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $this->duplicateEmail($attributes['email'], $exception);
        }
    }

    private function clientRole(int $companyId, bool $lock = false): ?Role
    {
        return Role::query()->where('company_id', $companyId)->where('guard_name', 'web')->where('name', 'client')
            ->when($lock, fn ($query) => $query->lockForUpdate())->with('permissions:id,name')->first();
    }

    private function createUser(array $attributes, int $companyId, User $actor, Role $role): User
    {
        if (User::withTrashed()->where('email', $attributes['email'])->exists()) {
            throw ValidationException::withMessages(['email' => ['This email is not available for a new login account.']]);
        }
        $parts = explode(' ', trim($attributes['name']), 2);
        $user = new User([
            'name' => $attributes['name'], 'first_name' => Str::substr($parts[0], 0, 100),
            'last_name' => isset($parts[1]) ? Str::substr($parts[1], 0, 100) : null,
            'email' => $attributes['email'], 'phone' => $attributes['phone'] ?? null,
            'password' => Hash::make($attributes['password']), 'role' => 'client', 'status' => User::STATUS_ACTIVE,
        ]);
        $user->company_id = $companyId;
        $user->save();
        $user->assignRole($role);
        activity('users')->causedBy($actor)->performedOn($user)->event('user.created')
            ->withProperties(['company_id' => $companyId, 'target_user_id' => $user->id,
                'changes' => ['fields' => ['name', 'first_name', 'last_name', 'email', 'phone'], 'role' => 'client']])->log('user.created');

        return $user;
    }

    private function duplicateEmail(string $email, UniqueConstraintViolationException $exception): never
    {
        if (User::withTrashed()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => ['This email is not available for a new login account.']]);
        }
        throw $exception;
    }

    public function update(Client $client, array $attributes, User $actor): Client
    {
        $attributes = Arr::only($attributes, self::CONTACT_FIELDS);
        $before = $client->only(array_keys($attributes));
        $client->fill($attributes)->save();
        $this->log($actor, $client, 'client.updated', ['before' => $before, 'after' => $client->only(array_keys($attributes))]);

        return $client;
    }

    public function delete(Client $client, User $actor): void
    {
        $this->log($actor, $client, 'client.deleted', ['projects_count' => $client->projects()->count()]);
        $client->delete();
    }

    private function log(User $actor, Client $client, string $event, array $changes): void
    {
        activity('clients')->causedBy($actor)->performedOn($client)->event($event)
            ->withProperties(['company_id' => $client->company_id, 'client_id' => $client->id, 'changes' => $changes])
            ->log($event);
    }
}
