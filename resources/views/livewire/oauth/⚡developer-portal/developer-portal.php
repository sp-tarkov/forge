<?php

declare(strict_types=1);

use App\Enums\OAuthClientEventType;
use App\Models\User;
use App\Services\OAuthClientEventRecorderService;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Client;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    /**
     * Maximum number of clients a single user may register. Matches ADR 0001 mitigations for self-serve
     * registration without admin approval.
     */
    public const int CLIENT_LIMIT = 5;

    /**
     * Phrases that, when present in a third-party client name, suggest the developer is impersonating an official
     * Forge property. Validation rejects matches outright; an admin override would have to flip the rule.
     *
     * @var array<int, string>
     */
    private const array RESERVED_NAME_FRAGMENTS = ['forge', 'tarkov', 'official', 'sptarkov'];

    /**
     * Form state for the create / edit modal.
     *
     * @var array{name: string, description: string, homepage_url: string, redirect_uris_raw: string, confidential: bool}
     */
    public array $form = [
        'name' => '',
        'description' => '',
        'homepage_url' => '',
        'redirect_uris_raw' => '',
        'confidential' => true,
    ];

    /**
     * The id of the client currently being edited, or `null` when the form is in "create new" mode.
     */
    public ?string $editingClientId = null;

    /**
     * Plaintext secret to flash once after creation or regeneration. Persisted across renders so the modal can
     * display it; cleared when the user dismisses the toast / closes the modal.
     */
    public ?string $plainSecret = null;

    public ?string $confirmingDeletionFor = null;

    public ?string $confirmingRegenerationFor = null;

    /**
     * @return Collection<int, Client>
     */
    public function getClientsProperty(): Collection
    {
        /** @var Collection<int, Client> $clients */
        $clients = $this->user->oauthApps()->orderBy('created_at', 'desc')->get();

        return $clients;
    }

    public function getUserProperty(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    public function getCanCreateProperty(): bool
    {
        return $this->clients->count() < self::CLIENT_LIMIT;
    }

    public function getClientLimitProperty(): int
    {
        return self::CLIENT_LIMIT;
    }

    /**
     * Begin editing an existing client; preloads the form with its current values.
     */
    public function editClient(string $clientId): void
    {
        $client = $this->user->oauthApps()->whereKey($clientId)->firstOrFail();

        $name = $client->getAttribute('name');
        $description = $client->getAttribute('description');
        $homepage = $client->getAttribute('homepage_url');

        $this->editingClientId = $clientId;
        $this->form = [
            'name' => is_string($name) ? $name : '',
            'description' => is_string($description) ? $description : '',
            'homepage_url' => is_string($homepage) ? $homepage : '',
            'redirect_uris_raw' => implode("\n", $this->redirectUrisArray($client)),
            'confidential' => $client->getAttribute('secret') !== null,
        ];

        $this->dispatch('open-developer-portal-modal');
    }

    /**
     * Open the create form. Also responds to the `open-create-oauth-app` event dispatched by the "Register new app"
     * button that lives in the page header bar, outside this component.
     */
    #[On('open-create-oauth-app')]
    public function startCreate(): void
    {
        if (! $this->canCreate) {
            Flux::toast(
                heading: __('App limit reached'),
                text: __('You have reached the maximum of :count apps. Delete one to register another.', ['count' => self::CLIENT_LIMIT]),
                variant: 'warning',
            );

            return;
        }

        $this->editingClientId = null;
        $this->form = [
            'name' => '',
            'description' => '',
            'homepage_url' => '',
            'redirect_uris_raw' => '',
            'confidential' => true,
        ];

        $this->dispatch('open-developer-portal-modal');
    }

    public function save(): void
    {
        $validated = $this->validateForm();
        /** @var array{name: string, description: ?string, homepage_url: ?string, redirect_uris: array<int, string>, confidential: bool} $validated */
        if ($this->editingClientId === null) {
            $this->createClient($validated);
        } else {
            $this->updateClient($this->editingClientId, $validated);
        }
    }

    public function confirmRegenerate(string $clientId): void
    {
        $this->confirmingRegenerationFor = $clientId;
        $this->dispatch('open-developer-portal-regenerate-modal');
    }

    public function regenerateSecret(): void
    {
        if ($this->confirmingRegenerationFor === null) {
            return;
        }

        $client = $this->user->oauthApps()->whereKey($this->confirmingRegenerationFor)->firstOrFail();

        if ($client->getAttribute('secret') === null) {
            $this->confirmingRegenerationFor = null;
            $this->dispatch('close-developer-portal-regenerate-modal');

            return;
        }

        $secret = Str::random(40);
        $client->forceFill(['secret' => hash('sha256', $secret)])->save();

        resolve(OAuthClientEventRecorderService::class)->record(
            event: OAuthClientEventType::SECRET_REGENERATED,
            client: $client,
            actor: $this->user,
        );

        $this->plainSecret = $secret;
        $this->editingClientId = $this->confirmingRegenerationFor;
        $this->confirmingRegenerationFor = null;

        $this->dispatch('close-developer-portal-regenerate-modal');
        $this->dispatch('show-developer-portal-secret');
    }

    public function confirmDeletion(string $clientId): void
    {
        $this->confirmingDeletionFor = $clientId;
        $this->dispatch('open-developer-portal-delete-modal');
    }

    public function deleteClient(): void
    {
        if ($this->confirmingDeletionFor === null) {
            return;
        }

        $client = $this->user->oauthApps()->whereKey($this->confirmingDeletionFor)->firstOrFail();
        $clientName = $client->getAttribute('name');
        $client->delete();

        resolve(OAuthClientEventRecorderService::class)->record(
            event: OAuthClientEventType::DELETED,
            client: null,
            actor: $this->user,
            metadata: ['client_id' => $this->confirmingDeletionFor, 'name' => is_string($clientName) ? $clientName : null],
        );

        $this->confirmingDeletionFor = null;
        Flux::toast(heading: __('OAuth App deleted'), text: __('The client and all of its tokens have been revoked.'), variant: 'success');
        $this->dispatch('close-developer-portal-delete-modal');
    }

    public function dismissSecret(): void
    {
        $this->plainSecret = null;
        $this->editingClientId = null;
        $this->dispatch('close-developer-portal-secret');
    }

    /**
     * @return array{name: string, description: ?string, homepage_url: ?string, redirect_uris: array<int, string>, confidential: bool}
     */
    private function validateForm(): array
    {
        if ($this->editingClientId === null && ! $this->canCreate) {
            $this->addError('form.name', __('You have reached the maximum of :count OAuth apps. Delete an unused one before creating another.', ['count' => self::CLIENT_LIMIT]));

            throw ValidationException::withMessages([
                'form.name' => __('You have reached the maximum of :count OAuth apps.', ['count' => self::CLIENT_LIMIT]),
            ]);
        }

        $split = preg_split('/\r\n|\r|\n/', $this->form['redirect_uris_raw']);
        /** @var array<int, string> $rawUris */
        $rawUris = collect(is_array($split) ? $split : [])
            ->map(fn (string $line): string => mb_trim($line))
            ->filter(fn (string $line): bool => $line !== '')
            ->values()
            ->all();

        $data = Validator::make([
            'name' => $this->form['name'],
            'description' => $this->form['description'] ?: null,
            'homepage_url' => $this->form['homepage_url'] ?: null,
            'redirect_uris' => $rawUris,
            'confidential' => (bool) $this->form['confidential'],
        ], [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'homepage_url' => ['nullable', 'url', 'max:255'],
            'redirect_uris' => ['array', 'min:1', 'max:10'],
            'redirect_uris.*' => ['required', 'url', 'max:255'],
            'confidential' => ['boolean'],
        ], [
            'redirect_uris.*.url' => __('Each redirect URI must be a valid URL (one per line).'),
        ])->validate();

        $name = is_string($data['name']) ? $data['name'] : '';
        $description = is_string($data['description'] ?? null) ? $data['description'] : null;
        $homepage = is_string($data['homepage_url'] ?? null) ? $data['homepage_url'] : null;
        /** @var array<int, string> $redirectUris */
        $redirectUris = is_array($data['redirect_uris'] ?? null) ? array_values(array_filter($data['redirect_uris'], is_string(...))) : [];

        $this->ensureNameIsNotReserved($name);
        $this->ensureRedirectUrisAreSafe($redirectUris);

        return [
            'name' => $name,
            'description' => $description,
            'homepage_url' => $homepage,
            'redirect_uris' => $redirectUris,
            'confidential' => (bool) $data['confidential'],
        ];
    }

    /**
     * Reject names that contain reserved fragments suggesting impersonation of an official Forge property.
     */
    private function ensureNameIsNotReserved(string $name): void
    {
        $lower = mb_strtolower($name);

        foreach (self::RESERVED_NAME_FRAGMENTS as $fragment) {
            if (str_contains($lower, $fragment)) {
                throw ValidationException::withMessages([
                    'form.name' => __('The name cannot contain ":fragment"; that is reserved for official Forge applications.', ['fragment' => $fragment]),
                ]);
            }
        }
    }

    /**
     * Reject IP-based redirects other than the RFC 8252 loopback addresses; common phishing payloads target raw
     * IP redirects to exfiltrate auth codes.
     *
     * @param  array<int, string>  $uris
     */
    private function ensureRedirectUrisAreSafe(array $uris): void
    {
        foreach ($uris as $uri) {
            $host = parse_url($uri, PHP_URL_HOST);

            if (! is_string($host)) {
                continue;
            }

            if (filter_var($host, FILTER_VALIDATE_IP) === false) {
                continue;
            }

            if (! in_array($host, ['127.0.0.1', '::1'], true)) {
                throw ValidationException::withMessages([
                    'form.redirect_uris_raw' => __('IP-based redirect URIs other than 127.0.0.1 are not allowed.'),
                ]);
            }
        }
    }

    /**
     * @param  array{name: string, description: ?string, homepage_url: ?string, redirect_uris: array<int, string>, confidential: bool}  $data
     */
    private function createClient(array $data): void
    {
        $secret = $data['confidential'] ? Str::random(40) : null;

        $client = new Client;
        $client->setAttribute('id', (string) Str::uuid());
        $client->setAttribute('owner_id', $this->user->getKey());
        $client->setAttribute('owner_type', $this->user->getMorphClass());
        $client->setAttribute('name', $data['name']);
        $client->setAttribute('description', $data['description']);
        $client->setAttribute('homepage_url', $data['homepage_url']);
        $client->setAttribute('secret', $secret === null ? null : hash('sha256', $secret));
        $client->setAttribute('provider', config('auth.guards.api.provider'));
        $client->setAttribute('redirect_uris', $data['redirect_uris']);
        $client->setAttribute('grant_types', ['authorization_code', 'refresh_token']);
        $client->setAttribute('revoked', false);
        $client->save();

        resolve(OAuthClientEventRecorderService::class)->record(
            event: OAuthClientEventType::CREATED,
            client: $client,
            actor: $this->user,
            metadata: ['confidential' => $data['confidential']],
        );

        $this->plainSecret = $secret;
        $this->editingClientId = null;

        if ($secret === null) {
            Flux::toast(heading: __('OAuth App created'), text: __('Your public client is ready to use.'), variant: 'success');
            $this->dispatch('close-developer-portal-modal');
        } else {
            // Close the editor first so the one-time secret modal is the only dialog left on screen, then reveal it.
            $this->dispatch('close-developer-portal-modal');
            $this->dispatch('show-developer-portal-secret');
        }
    }

    /**
     * @param  array{name: string, description: ?string, homepage_url: ?string, redirect_uris: array<int, string>, confidential: bool}  $data
     */
    private function updateClient(string $clientId, array $data): void
    {
        $client = $this->user->oauthApps()->whereKey($clientId)->firstOrFail();

        $before = [
            'name' => $client->getAttribute('name'),
            'description' => $client->getAttribute('description'),
            'homepage_url' => $client->getAttribute('homepage_url'),
            'redirect_uris' => $client->getAttribute('redirect_uris'),
        ];

        $client->forceFill([
            'name' => $data['name'],
            'description' => $data['description'],
            'homepage_url' => $data['homepage_url'],
            'redirect_uris' => $data['redirect_uris'],
        ])->save();

        $changed = array_keys(array_filter([
            'name' => $before['name'] !== $data['name'],
            'description' => $before['description'] !== $data['description'],
            'homepage_url' => $before['homepage_url'] !== $data['homepage_url'],
            'redirect_uris' => $before['redirect_uris'] !== $data['redirect_uris'],
        ]));

        resolve(OAuthClientEventRecorderService::class)->record(
            event: OAuthClientEventType::UPDATED,
            client: $client,
            actor: $this->user,
            metadata: ['changed_fields' => $changed],
        );

        Flux::toast(heading: __('OAuth App updated'), text: __('Changes saved.'), variant: 'success');

        $this->editingClientId = null;
        $this->dispatch('close-developer-portal-modal');
    }

    /**
     * @return array<int, string>
     */
    private function redirectUrisArray(Client $client): array
    {
        $value = $client->getAttribute('redirect_uris');

        if (is_array($value)) {
            return array_values(array_filter(array_map(static fn (mixed $v): string => is_string($v) ? $v : '', $value)));
        }

        return [];
    }
};
