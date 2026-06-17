<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Laravel\Passport\Scope;
use Laravel\Passport\Token;
use Livewire\Component;

/**
 * Lists every OAuth client the user has granted access to, grouped by client, with per-device rows so the user can
 * revoke a single launcher installation without disturbing other devices. Matches the per-device decision in
 * ADR 0001.
 */
new class extends Component
{
    public ?string $confirmingRevocationFor = null;

    public ?string $confirmingClientRevocationFor = null;

    public function getUserProperty(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    /**
     * Map of scope id => human-readable description, mirroring the consent screen so the language a user saw when
     * granting access matches what they see here. Unknown ids fall back to the raw id in the view.
     *
     * @return array<string, string>
     */
    public function getScopeDescriptionsProperty(): array
    {
        return Passport::scopes()
            ->mapWithKeys(fn (Scope $scope): array => [$scope->id => $scope->description])
            ->all();
    }

    /**
     * @return Collection<int, array{client: Client, tokens: Collection<int, Token>, totalIssued: int, lastUsedAt: ?CarbonImmutable, isFirstParty: bool}>
     */
    public function getConnectionsProperty(): Collection
    {
        /** @var Collection<int, Token> $tokens */
        $tokens = $this->user->passportTokens()
            ->where('revoked', false)
            ->with('client')
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get();

        return $tokens->groupBy(function (Token $token): string {
            $clientId = $token->getAttribute('client_id');

            return is_string($clientId) ? $clientId : '';
        })
            ->map(function (Collection $tokensForClient): array {
                /** @var Token $first */
                $first = $tokensForClient->first();
                /** @var ?Client $client */
                $client = $first->client;

                // Pre-compute human-friendly timestamps on each token so the view never has to touch raw strings.
                // Passport's Token model does not cast `last_used_at` or `created_at`, so we do it here.
                $tokensForClient->each(function (Token $token): void {
                    $lastUsed = $this->parseTimestamp($token->getAttribute('last_used_at'));
                    $created = $this->parseTimestamp($token->getAttribute('created_at'));

                    $token->setAttribute('lastUsedHuman', $lastUsed?->diffForHumans());
                    $token->setAttribute('createdHuman', $created?->diffForHumans() ?? '');
                });

                $lastUsedAt = $tokensForClient
                    ->map(fn (Token $token): ?CarbonImmutable => $this->parseTimestamp($token->getAttribute('last_used_at')))
                    ->filter()
                    ->max();

                return [
                    'client' => $client,
                    'tokens' => $tokensForClient,
                    'totalIssued' => $tokensForClient->count(),
                    'lastUsedAt' => $lastUsedAt,
                    'isFirstParty' => $client?->firstParty() ?? false,
                ];
            })
            ->filter(fn (array $entry): bool => $entry['client'] instanceof Client)
            ->sortByDesc(fn (array $entry): int => $entry['lastUsedAt']?->getTimestamp() ?? 0)
            ->values();
    }

    public function confirmRevokeDevice(string $tokenId): void
    {
        $this->confirmingRevocationFor = $tokenId;
        $this->dispatch('open-connected-apps-revoke-device-modal');
    }

    public function revokeDevice(): void
    {
        if ($this->confirmingRevocationFor === null) {
            return;
        }

        Passport::token()->newQuery()
            ->where('user_id', $this->user->getKey())
            ->whereKey($this->confirmingRevocationFor)
            ->update(['revoked' => true]);

        $this->revokeRefreshTokensForAccessTokens([$this->confirmingRevocationFor]);

        $this->confirmingRevocationFor = null;
        Flux::toast(heading: __('Device revoked'), text: __('That device can no longer use the API until you authorize it again.'), variant: 'success');
        $this->dispatch('close-connected-apps-revoke-device-modal');
    }

    public function confirmRevokeClient(string $clientId): void
    {
        $this->confirmingClientRevocationFor = $clientId;
        $this->dispatch('open-connected-apps-revoke-client-modal');
    }

    public function revokeClient(): void
    {
        if ($this->confirmingClientRevocationFor === null) {
            return;
        }

        /** @var array<int, string> $tokenIds */
        $tokenIds = Passport::token()->newQuery()
            ->where('user_id', $this->user->getKey())
            ->where('client_id', $this->confirmingClientRevocationFor)
            ->where('revoked', false)
            ->pluck('id')
            ->map(fn (mixed $id): string => is_string($id) ? $id : '')
            ->all();

        if ($tokenIds === []) {
            $this->confirmingClientRevocationFor = null;
            $this->dispatch('close-connected-apps-revoke-client-modal');

            return;
        }

        Passport::token()->newQuery()->whereIn('id', $tokenIds)->update(['revoked' => true]);

        $this->revokeRefreshTokensForAccessTokens($tokenIds);

        $this->confirmingClientRevocationFor = null;
        Flux::toast(heading: __('App revoked'), text: __('All access from this application has been removed.'), variant: 'success');
        $this->dispatch('close-connected-apps-revoke-client-modal');
    }

    /**
     * Passport's `Token` model does not cast `last_used_at` or `created_at` to a date, so we parse manually when
     * displaying. Returns null for empty/null values.
     */
    private function parseTimestamp(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        return is_string($value) ? CarbonImmutable::parse($value) : null;
    }

    /**
     * @param  array<int, string>  $accessTokenIds
     */
    private function revokeRefreshTokensForAccessTokens(array $accessTokenIds): void
    {
        Passport::refreshToken()->newQuery()
            ->whereIn('access_token_id', $accessTokenIds)
            ->update(['revoked' => true]);
    }
};
