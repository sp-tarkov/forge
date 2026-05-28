<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

#[Description('Seed or refresh the first-party Forge Launcher OAuth client (public, PKCE, loopback redirect).')]
#[Signature('forge:create-launcher-client {--name=The Forge Launcher : The display name of the client} {--redirect-uri=http://127.0.0.1/callback : Loopback redirect URI; only the port may vary at runtime per RFC 8252, scheme/host/path must match exactly}')]
final class CreateLauncherClient extends Command
{
    public function handle(ClientRepository $clients): int
    {
        $name = (string) $this->option('name');
        $redirectUri = (string) $this->option('redirect-uri');

        $existing = Client::query()
            ->where('name', $name)
            ->whereNull('secret')
            ->first();

        if ($existing instanceof Client) {
            $existing->forceFill([
                'redirect_uris' => [$redirectUri],
                'revoked' => false,
            ])->save();

            /** @var string $existingId Passport stores client IDs as UUID strings. */
            $existingId = $existing->getKey();
            $this->info(sprintf('Updated existing launcher client [%s].', $existingId));

            return self::SUCCESS;
        }

        $client = $clients->createAuthorizationCodeGrantClient(
            name: $name,
            redirectUris: [$redirectUri],
            confidential: false,
        );

        /** @var string $clientId Passport stores client IDs as UUID strings. */
        $clientId = $client->getKey();
        $this->info('Created the Forge Launcher OAuth client.');
        $this->line(sprintf('  client_id:    %s', $clientId));
        $this->line(sprintf('  redirect_uri: %s', $redirectUri));
        $this->line('  grant:        authorization_code with PKCE (public client, no secret)');

        return self::SUCCESS;
    }
}
