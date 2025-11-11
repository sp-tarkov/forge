<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use Illuminate\Support\Arr;
use Laravel\Jetstream\Http\Livewire\ApiTokenManager as BaseApiTokenManager;
use Laravel\Jetstream\Jetstream;
use Override;

class ApiTokenManager extends BaseApiTokenManager
{
    private const READ_ABILITY = 'read';

    #[Override]
    public function mount(): void
    {
        parent::mount();

        $this->createApiTokenForm['permissions'] = $this->ensureReadAbility(
            $this->createApiTokenForm['permissions']
        );
    }

    #[Override]
    public function manageApiTokenPermissions($tokenId): void
    {
        parent::manageApiTokenPermissions($tokenId);

        $this->updateApiTokenForm['permissions'] = $this->ensureReadAbility(
            $this->updateApiTokenForm['permissions']
        );
    }

    #[Override]
    public function createApiToken(): void
    {
        $this->createApiTokenForm['permissions'] = $this->ensureReadAbility(
            $this->createApiTokenForm['permissions']
        );

        parent::createApiToken();
    }

    #[Override]
    public function updateApiToken(): void
    {
        $this->updateApiTokenForm['permissions'] = $this->ensureReadAbility(
            $this->updateApiTokenForm['permissions']
        );

        $this->managingPermissionsFor->forceFill([
            'abilities' => Jetstream::validPermissions($this->updateApiTokenForm['permissions']),
        ])->save();

        $this->managingApiTokenPermissions = false;
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
            $permissions = Arr::prepend($permissions, self::READ_ABILITY);
        }

        return array_values(array_unique($permissions));
    }
}
