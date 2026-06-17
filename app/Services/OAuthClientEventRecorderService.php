<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OAuthClientEventType;
use App\Models\OAuthClientEvent;
use App\Models\User;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Client;

/**
 * Centralizes writes to the OAuth-client audit log so callers (the Developer Portal Livewire component, an admin
 * moderation view, future automated revocations) don't each have to remember to capture IP and user agent.
 */
final class OAuthClientEventRecorderService
{
    /**
     * @param  array<string, mixed>  $metadata  Optional extra context; e.g. which fields changed on an update.
     */
    public function record(OAuthClientEventType $event, ?Client $client = null, ?User $actor = null, array $metadata = []): OAuthClientEvent
    {
        $request = $this->resolveRequest();
        $actor ??= $this->resolveActor();

        $row = new OAuthClientEvent;
        $row->setAttribute('client_id', $client?->getKey());
        $row->setAttribute('actor_user_id', $actor?->getKey());
        $row->setAttribute('event', $event);
        $row->setAttribute('ip', $request?->ip());
        $row->setAttribute('user_agent', $request?->userAgent());
        $row->setAttribute('metadata', $metadata === [] ? null : $metadata);
        $row->save();

        return $row;
    }

    private function resolveRequest(): ?Request
    {
        $container = Container::getInstance();

        return $container->bound(Request::class) ? $container->make(Request::class) : null;
    }

    private function resolveActor(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
