<?php

namespace App\Services;

use App\Models\Client;
use App\Models\User;

class ClientService
{
    public function create(array $attributes, int $companyId, User $actor): Client
    {
        $client = new Client($attributes);
        $client->company_id = $companyId;
        $client->save();
        $this->log($actor, $client, 'client.created', ['after' => $client->only(array_keys($attributes))]);

        return $client;
    }

    public function update(Client $client, array $attributes, User $actor): Client
    {
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
