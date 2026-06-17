<?php

declare(strict_types=1);

use App\Enums\OAuthClientEventType;
use App\Models\OAuthClientEvent;
use App\Models\User;
use Laravel\Passport\Client;
use Livewire\Livewire;

describe('OAuth client audit log', function (): void {
    it('records a CREATED event when a client is registered', function (): void {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('oauth.developer-portal')
            ->set('form.name', 'New App')
            ->set('form.redirect_uris_raw', 'https://example.com/cb')
            ->set('form.confidential', true)
            ->call('save');

        $client = Client::query()->where('owner_id', $user->getKey())->firstOrFail();
        $event = OAuthClientEvent::query()->where('event', OAuthClientEventType::CREATED->value)->firstOrFail();

        expect($event->getAttribute('actor_user_id'))->toBe($user->getKey());
        expect($event->getAttribute('client_id'))->toBe($client->getKey());
        expect($event->getAttribute('event'))->toBe(OAuthClientEventType::CREATED);
        expect($event->getAttribute('metadata'))->toBe(['confidential' => true]);
    });

    it('records an UPDATED event with changed fields', function (): void {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('oauth.developer-portal')
            ->set('form.name', 'Original')
            ->set('form.redirect_uris_raw', 'https://example.com/cb')
            ->call('save');

        $client = Client::query()->where('owner_id', $user->getKey())->firstOrFail();

        Livewire::actingAs($user)
            ->test('oauth.developer-portal')
            ->call('editClient', $client->getKey())
            ->set('form.name', 'Renamed')
            ->set('form.description', 'A description was added.')
            ->call('save');

        $event = OAuthClientEvent::query()->where('event', OAuthClientEventType::UPDATED->value)->firstOrFail();

        $metadata = $event->getAttribute('metadata');
        expect($metadata)->toBeArray();
        expect($metadata['changed_fields'] ?? [])->toContain('name');
        expect($metadata['changed_fields'] ?? [])->toContain('description');
    });

    it('records a SECRET_REGENERATED event', function (): void {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('oauth.developer-portal')
            ->set('form.name', 'Rotating Client')
            ->set('form.redirect_uris_raw', 'https://example.com/cb')
            ->set('form.confidential', true)
            ->call('save');

        $client = Client::query()->where('owner_id', $user->getKey())->firstOrFail();

        Livewire::actingAs($user)
            ->test('oauth.developer-portal')
            ->call('confirmRegenerate', $client->getKey())
            ->call('regenerateSecret');

        expect(OAuthClientEvent::query()->where('event', OAuthClientEventType::SECRET_REGENERATED->value)->where('client_id', $client->getKey())->exists())->toBeTrue();
    });

    it('records a DELETED event with the prior client_id and name in metadata', function (): void {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('oauth.developer-portal')
            ->set('form.name', 'About To Delete')
            ->set('form.redirect_uris_raw', 'https://example.com/cb')
            ->call('save');

        $client = Client::query()->where('owner_id', $user->getKey())->firstOrFail();
        $clientId = $client->getKey();

        Livewire::actingAs($user)
            ->test('oauth.developer-portal')
            ->call('confirmDeletion', $clientId)
            ->call('deleteClient');

        $event = OAuthClientEvent::query()
            ->where('event', OAuthClientEventType::DELETED->value)
            ->latest('id')
            ->firstOrFail();

        $metadata = $event->getAttribute('metadata');
        expect($metadata)->toBeArray();
        expect($metadata['client_id'] ?? null)->toBe($clientId);
        expect($metadata['name'] ?? null)->toBe('About To Delete');
    });

    it('captures the actors request context (IP) on the audit row', function (): void {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('oauth.developer-portal')
            ->set('form.name', 'Tracked')
            ->set('form.redirect_uris_raw', 'https://example.com/cb')
            ->call('save');

        $event = OAuthClientEvent::query()->where('event', OAuthClientEventType::CREATED->value)->firstOrFail();

        // The Livewire test harness uses an in-process request whose IP defaults to 127.0.0.1; the important
        // invariant is that we persist *something* rather than null. Production traffic carries real client IPs
        // via the `trustProxies` middleware.
        expect($event->getAttribute('ip'))->toBeString();
        expect((string) $event->getAttribute('ip'))->not->toBe('');
    });

    it('survives client deletion via the nullable client_id', function (): void {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('oauth.developer-portal')
            ->set('form.name', 'Will Be Gone')
            ->set('form.redirect_uris_raw', 'https://example.com/cb')
            ->call('save');

        $client = Client::query()->where('owner_id', $user->getKey())->firstOrFail();
        $clientId = $client->getKey();

        $client->delete();

        // The CREATED event row still exists, but its client_id may now reference a deleted client.
        $createdEvent = OAuthClientEvent::query()->where('client_id', $clientId)->where('event', OAuthClientEventType::CREATED->value)->first();
        expect($createdEvent)->not->toBeNull();
        expect($createdEvent?->getAttribute('client_id'))->toBe($clientId);
    });
});
