<?php

declare(strict_types=1);

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Component;

new class extends Component
{
    /**
     * The available API token permissions.
     *
     * @var array<int, string>
     */
    public const array PERMISSIONS = ['create', 'read', 'update', 'delete'];

    /**
     * The default permissions for new API tokens.
     *
     * @var array<int, string>
     */
    private const array DEFAULT_PERMISSIONS = ['read'];

    private const string READ_ABILITY = 'read';

    /**
     * The create API token form state.
     *
     * @var array<string, mixed>
     */
    public array $createApiTokenForm = [
        'name' => '',
        'permissions' => [],
    ];

    /**
     * Indicates if the plain text token is being displayed to the user.
     */
    public bool $displayingToken = false;

    /**
     * The plain text token value.
     */
    public ?string $plainTextToken = null;

    /**
     * Indicates if the user is currently managing an API token's permissions.
     */
    public bool $managingApiTokenPermissions = false;

    /**
     * The token that is currently having its permissions managed.
     */
    public ?PersonalAccessToken $managingPermissionsFor = null;

    /**
     * The update API token form state.
     *
     * @var array<string, mixed>
     */
    public array $updateApiTokenForm = [
        'permissions' => [],
    ];

    /**
     * Indicates if the application is confirming if an API token should be deleted.
     */
    public bool $confirmingApiTokenDeletion = false;

    /**
     * The ID of the API token being deleted.
     */
    public ?int $apiTokenIdBeingDeleted = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->createApiTokenForm['permissions'] = $this->ensureReadAbility(self::DEFAULT_PERMISSIONS);
    }

    /**
     * Create a new API token.
     */
    public function createApiToken(): void
    {
        $this->resetErrorBag();

        /** @var array<int, string> $permissions */
        $permissions = $this->createApiTokenForm['permissions'];
        $this->createApiTokenForm['permissions'] = $this->ensureReadAbility($permissions);

        Validator::make(
            [
                'name' => $this->createApiTokenForm['name'],
            ],
            [
                'name' => ['required', 'string', 'max:255'],
            ],
        )->validateWithBag('createApiToken');

        /** @var string $name */
        $name = $this->createApiTokenForm['name'];
        /** @var array<int, string> $currentPermissions */
        $currentPermissions = $this->createApiTokenForm['permissions'];
        $this->displayTokenValue($this->user->createToken($name, $this->validPermissions($currentPermissions)));

        $this->createApiTokenForm['name'] = '';
        $this->createApiTokenForm['permissions'] = $this->ensureReadAbility(self::DEFAULT_PERMISSIONS);

        Flux::toast(heading: 'Token Created', text: 'Your API token has been created.', variant: 'success');
    }

    /**
     * Allow the given token's permissions to be managed.
     */
    public function manageApiTokenPermissions(int $tokenId): void
    {
        $this->managingApiTokenPermissions = true;

        $this->managingPermissionsFor = $this->user->tokens()->where('id', $tokenId)->firstOrFail();

        /** @var array<int, string> $abilities */
        $abilities = $this->managingPermissionsFor->abilities;
        $this->updateApiTokenForm['permissions'] = $this->ensureReadAbility($abilities);
    }

    /**
     * Update the API token's permissions.
     */
    public function updateApiToken(): void
    {
        /** @var array<int, string> $updatePermissions */
        $updatePermissions = $this->updateApiTokenForm['permissions'];
        $this->updateApiTokenForm['permissions'] = $this->ensureReadAbility($updatePermissions);

        if (! $this->managingPermissionsFor instanceof PersonalAccessToken) {
            return;
        }

        $this->managingPermissionsFor
            ->forceFill([
                'abilities' => $this->validPermissions($this->updateApiTokenForm['permissions']),
            ])
            ->save();

        $this->managingApiTokenPermissions = false;
    }

    /**
     * Confirm that the given API token should be deleted.
     */
    public function confirmApiTokenDeletion(int $tokenId): void
    {
        $this->confirmingApiTokenDeletion = true;

        $this->apiTokenIdBeingDeleted = $tokenId;
    }

    /**
     * Delete the API token.
     */
    public function deleteApiToken(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $user->tokens()->where('id', $this->apiTokenIdBeingDeleted)->first()?->delete();

        $user->load('tokens');

        $this->confirmingApiTokenDeletion = false;

        $this->managingPermissionsFor = null;
    }

    /**
     * Get the current user of the application.
     */
    public function getUserProperty(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    /**
     * Get the available permissions.
     *
     * @return array<int, string>
     */
    public function getPermissionsProperty(): array
    {
        return self::PERMISSIONS;
    }

    /**
     * Display the token value to the user.
     */
    protected function displayTokenValue(NewAccessToken $token): void
    {
        $this->displayingToken = true;

        $this->plainTextToken = explode('|', $token->plainTextToken, 2)[1];

        $this->dispatch('showing-token-modal');
    }

    /**
     * Filter the given permissions to only valid ones.
     *
     * @param  array<int, string>  $permissions
     * @return array<int, string>
     */
    private function validPermissions(array $permissions): array
    {
        return array_values(array_intersect($permissions, self::PERMISSIONS));
    }

    /**
     * Ensure the read ability is always present.
     *
     * @param  array<int, string>  $permissions
     * @return array<int, string>
     */
    private function ensureReadAbility(array $permissions): array
    {
        if (! in_array(self::READ_ABILITY, $permissions, true)) {
            array_unshift($permissions, self::READ_ABILITY);
        }

        return array_values(array_unique($permissions));
    }
};
