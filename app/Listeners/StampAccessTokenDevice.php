<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Support\Facades\DB;
use Laravel\Passport\Events\AccessTokenCreated;
use Laravel\Passport\Passport;

/**
 * Stamps the launcher-supplied `device_name` onto the freshly-issued access token. The label can come from two
 * places:
 *
 * - Authorization Code grant: written onto the auth code row during persistence, and read back here while it is
 *   still unrevoked (league/oauth2-server issues the access token before revoking the auth code).
 * - Refresh Token grant: there is no auth code in play, so we carry the label forward from the previous active
 *   token for the same user + client.
 */
final class StampAccessTokenDevice
{
    public function handle(AccessTokenCreated $event): void
    {
        if ($event->userId === null) {
            return;
        }

        $deviceName = $this->deviceNameFromAuthCode($event) ?? $this->deviceNameFromPriorToken($event);

        if ($deviceName === null || $deviceName === '') {
            return;
        }

        DB::table(Passport::token()->getTable())
            ->where('id', $event->tokenId)
            ->update(['device_name' => $deviceName]);
    }

    private function deviceNameFromAuthCode(AccessTokenCreated $event): ?string
    {
        $authCode = Passport::authCode()->newQuery()
            ->where('user_id', $event->userId)
            ->where('client_id', $event->clientId)
            ->where('revoked', false)
            ->latest('id')
            ->first();

        $deviceName = $authCode?->getAttribute('device_name');

        return is_string($deviceName) && $deviceName !== '' ? $deviceName : null;
    }

    private function deviceNameFromPriorToken(AccessTokenCreated $event): ?string
    {
        $prior = Passport::token()->newQuery()
            ->where('user_id', $event->userId)
            ->where('client_id', $event->clientId)
            ->where('id', '!=', $event->tokenId)
            ->whereNotNull('device_name')
            ->latest('created_at')
            ->first();

        $deviceName = $prior?->getAttribute('device_name');

        return is_string($deviceName) && $deviceName !== '' ? $deviceName : null;
    }
}
