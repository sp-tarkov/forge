<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Livewire\Livewire;

/**
 * Persist a number of bare OAuth clients owned by the given user using direct inserts to keep the test fast.
 */
function seedOAuthClientsForUser(User $user, int $count): void
{
    for ($i = 1; $i <= $count; $i++) {
        $client = new Client;
        $client->setAttribute('id', (string) Str::uuid());
        $client->setAttribute('owner_id', $user->getKey());
        $client->setAttribute('owner_type', $user->getMorphClass());
        $client->setAttribute('name', 'Existing App '.$i);
        $client->setAttribute('redirect_uris', ['https://example.com/cb']);
        $client->setAttribute('grant_types', ['authorization_code', 'refresh_token']);
        $client->setAttribute('revoked', false);
        $client->save();
    }
}

describe('Developer Portal', function (): void {
    it('redirects unauthenticated visitors to login', function (): void {
        $this->get(route('oauth.developer-portal'))->assertRedirect(route('login'));
    });

    it('renders for an authenticated, verified user', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('oauth.developer-portal'))
            ->assertSuccessful()
            ->assertSee('OAuth Apps')
            ->assertSee('No apps yet');
    });

    it('explains the confidential client choice in plain language', function (): void {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('oauth.developer-portal')
            ->assertSee('Confidential client')
            ->assertSee('desktop launchers')
            ->assertSee('cannot be changed after the app is created');
    });

    it('creates a confidential client and reveals the plaintext secret once', function (): void {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test('oauth.developer-portal')
            ->set('form.name', 'My Cool App')
            ->set('form.description', 'Pings the Forge API.')
            ->set('form.homepage_url', 'https://example.com')
            ->set('form.redirect_uris_raw', "https://example.com/oauth/callback\n")
            ->set('form.confidential', true)
            ->call('save');

        $component->assertHasNoErrors();
        $component->assertSet('plainSecret', fn ($v): bool => is_string($v) && mb_strlen($v) >= 40);

        $client = Client::query()->where('owner_id', $user->getKey())->first();
        expect($client)->not->toBeNull();
        expect($client?->name)->toBe('My Cool App');
        expect($client?->description)->toBe('Pings the Forge API.');
        expect($client?->homepage_url)->toBe('https://example.com');
        expect($client?->secret)->not->toBeNull();
        expect($client?->secret)->not->toBe($component->get('plainSecret'));
    });

    it('creates a public PKCE client with no secret', function (): void {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('oauth.developer-portal')
            ->set('form.name', 'My Public Launcher')
            ->set('form.redirect_uris_raw', 'http://127.0.0.1/callback')
            ->set('form.confidential', false)
            ->call('save')
            ->assertHasNoErrors();

        $client = Client::query()->where('owner_id', $user->getKey())->first();
        expect($client?->secret)->toBeNull();
    });

    it('rejects a name containing a reserved fragment', function (): void {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('oauth.developer-portal')
            ->set('form.name', 'Official Forge Helper')
            ->set('form.redirect_uris_raw', 'https://example.com/cb')
            ->call('save')
            ->assertHasErrors(['form.name']);

        expect(Client::query()->where('owner_id', $user->getKey())->exists())->toBeFalse();
    });

    it('rejects raw-IP redirect URIs outside the loopback range', function (): void {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('oauth.developer-portal')
            ->set('form.name', 'Phisher')
            ->set('form.redirect_uris_raw', 'http://203.0.113.10/grab')
            ->call('save')
            ->assertHasErrors(['form.redirect_uris_raw']);
    });

    it('accepts loopback HTTP URIs', function (): void {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('oauth.developer-portal')
            ->set('form.name', 'Local Loopback App')
            ->set('form.redirect_uris_raw', "http://127.0.0.1/callback\nhttp://[::1]/callback")
            ->set('form.confidential', false)
            ->call('save')
            ->assertHasNoErrors();
    });

    it('updates an existing client', function (): void {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('oauth.developer-portal')
            ->set('form.name', 'Initial Name')
            ->set('form.redirect_uris_raw', 'https://example.com/cb')
            ->call('save');

        $client = Client::query()->where('owner_id', $user->getKey())->firstOrFail();

        Livewire::actingAs($user)
            ->test('oauth.developer-portal')
            ->call('editClient', $client->getKey())
            ->set('form.name', 'Renamed App')
            ->set('form.description', 'Now with a description.')
            ->call('save')
            ->assertHasNoErrors();

        expect($client->fresh()?->name)->toBe('Renamed App');
        expect($client->fresh()?->description)->toBe('Now with a description.');
    });

    it('regenerates the secret for a confidential client', function (): void {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test('oauth.developer-portal')
            ->set('form.name', 'Rotating App')
            ->set('form.redirect_uris_raw', 'https://example.com/cb')
            ->set('form.confidential', true)
            ->call('save');

        $client = Client::query()->where('owner_id', $user->getKey())->firstOrFail();
        $oldHashedSecret = $client->secret;

        $component->call('confirmRegenerate', $client->getKey())
            ->assertSet('confirmingRegenerationFor', $client->getKey())
            ->assertDispatched('open-developer-portal-regenerate-modal')
            ->call('regenerateSecret')
            ->assertDispatched('close-developer-portal-regenerate-modal')
            ->assertDispatched('show-developer-portal-secret')
            ->assertSet('confirmingRegenerationFor', null);

        expect($client->fresh()?->secret)->not->toBe($oldHashedSecret);
        expect($component->get('plainSecret'))->toBeString();
    });

    it('refuses to regenerate the secret on a public client', function (): void {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('oauth.developer-portal')
            ->set('form.name', 'Public Only')
            ->set('form.redirect_uris_raw', 'http://127.0.0.1/cb')
            ->set('form.confidential', false)
            ->call('save');

        $client = Client::query()->where('owner_id', $user->getKey())->firstOrFail();

        Livewire::actingAs($user)
            ->test('oauth.developer-portal')
            ->call('confirmRegenerate', $client->getKey())
            ->call('regenerateSecret');

        expect($client->fresh()?->secret)->toBeNull();
    });

    it('deletes a client (after confirmation)', function (): void {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('oauth.developer-portal')
            ->set('form.name', 'Will be deleted')
            ->set('form.redirect_uris_raw', 'https://example.com/cb')
            ->call('save');

        $client = Client::query()->where('owner_id', $user->getKey())->firstOrFail();

        Livewire::actingAs($user)
            ->test('oauth.developer-portal')
            ->call('confirmDeletion', $client->getKey())
            ->call('deleteClient');

        expect(Client::query()->whereKey($client->getKey())->exists())->toBeFalse();
    });

    it('refuses to operate on another users client', function (): void {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        Livewire::actingAs($owner)
            ->test('oauth.developer-portal')
            ->set('form.name', 'Owners App')
            ->set('form.redirect_uris_raw', 'https://example.com/cb')
            ->call('save');

        $client = Client::query()->where('owner_id', $owner->getKey())->firstOrFail();

        // Stranger tries to delete owner's client; expect a model-not-found.
        expect(fn () => Livewire::actingAs($stranger)
            ->test('oauth.developer-portal')
            ->call('confirmDeletion', $client->getKey())
            ->call('deleteClient'))
            ->toThrow(ModelNotFoundException::class);

        expect(Client::query()->whereKey($client->getKey())->exists())->toBeTrue();
    });

    it('enforces the per-user client limit', function (): void {
        $user = User::factory()->create();

        seedOAuthClientsForUser($user, 5);

        Livewire::actingAs($user)
            ->test('oauth.developer-portal')
            ->assertSet('canCreate', false)
            ->set('form.name', 'One Too Many')
            ->set('form.redirect_uris_raw', 'https://example.com/cb')
            ->call('save')
            ->assertHasErrors(['form.name']);
    });

    it('shows a notice once the app limit is reached', function (): void {
        $user = User::factory()->create();

        seedOAuthClientsForUser($user, 5);

        Livewire::actingAs($user)
            ->test('oauth.developer-portal')
            ->assertSet('canCreate', false)
            ->assertSee('reached the maximum of 5 apps');
    });

    it('opens the create form when the header register button dispatches its event', function (): void {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('oauth.developer-portal')
            ->dispatch('open-create-oauth-app')
            ->assertSet('editingClientId', null)
            ->assertDispatched('open-developer-portal-modal');
    });

    it('does not open the create form via the header button once the limit is reached', function (): void {
        $user = User::factory()->create();

        seedOAuthClientsForUser($user, 5);

        Livewire::actingAs($user)
            ->test('oauth.developer-portal')
            ->dispatch('open-create-oauth-app')
            ->assertNotDispatched('open-developer-portal-modal');
    });

    it('reveals the secret in its own modal and dismisses only that modal', function (): void {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('oauth.developer-portal')
            ->set('form.name', 'Secret Holder')
            ->set('form.redirect_uris_raw', 'https://example.com/cb')
            ->set('form.confidential', true)
            ->call('save')
            // Creating a confidential client closes the editor and opens the dedicated one-time secret modal.
            ->assertDispatched('close-developer-portal-modal')
            ->assertDispatched('show-developer-portal-secret')
            ->assertSet('plainSecret', fn ($v): bool => is_string($v))
            // Dismissing the secret closes the secret modal (not the already-closed editor) and clears the secret.
            ->call('dismissSecret')
            ->assertDispatched('close-developer-portal-secret')
            ->assertSet('plainSecret', null);
    });
});
