<?php

declare(strict_types=1);

namespace App\Support\Passport;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Laravel\Passport\Bridge\AuthCodeRepository as BridgeAuthCodeRepository;
use Laravel\Passport\Passport;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;

/**
 * Extends Passport's default `AuthCodeRepository` so the `device_name` query parameter from the consent screen is
 * persisted onto the auth code row. The {@see \App\Listeners\StampAccessTokenDevice} listener then carries the
 * value forward when the code is exchanged for an access token. See ADR 0001.
 */
final class AuthCodeRepository extends BridgeAuthCodeRepository
{
    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        Passport::authCode()->forceFill([
            'id' => $authCodeEntity->getIdentifier(),
            'user_id' => $authCodeEntity->getUserIdentifier(),
            'client_id' => $authCodeEntity->getClient()->getIdentifier(),
            'scopes' => json_encode($authCodeEntity->getScopes()),
            'revoked' => false,
            'expires_at' => $authCodeEntity->getExpiryDateTime(),
            'device_name' => $this->deviceNameFromRequest(),
        ])->save();
    }

    /**
     * The launcher passes `device_name` as a query parameter on `/oauth/authorize`, which we forward through the
     * consent form as a hidden field; either way it ends up on the active request when this method runs.
     */
    private function deviceNameFromRequest(): ?string
    {
        if (! Container::getInstance()->bound(Request::class)) {
            return null;
        }

        $request = Container::getInstance()->make(Request::class);

        $name = $request->query('device_name') ?? $request->input('device_name');

        if (! is_string($name) || mb_trim($name) === '') {
            return null;
        }

        return mb_substr(mb_trim($name), 0, 120);
    }
}
