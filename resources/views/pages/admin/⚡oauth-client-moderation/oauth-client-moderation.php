<?php

declare(strict_types=1);

use App\Enums\OAuthClientEventType;
use App\Models\OAuthClientEvent;
use App\Models\User;
use App\Services\OAuthClientEventRecorderService;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin-only OAuth client moderation view. Lists every registered client (first- AND third-party) with filters by
 * ownership, party type, revocation status, and recent activity. Per-client actions: view the audit log, revoke
 * the client (admin-side, distinct from the user-side delete in the Developer Portal). See ADR 0001's mitigations
 * for self-serve registration without admin approval.
 */
new #[Layout('layouts::base')] #[Title('OAuth Clients - The Forge')] class extends Component
{
    use WithPagination;

    public string $search = '';

    /** One of '', 'first_party', 'third_party'. */
    public string $partyFilter = '';

    /** One of '', 'active', 'revoked'. */
    public string $statusFilter = '';

    public ?string $clientForAudit = null;

    public ?string $confirmingRevocationFor = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPartyFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Client>
     */
    public function getClientsProperty(): LengthAwarePaginator
    {
        return Client::query()
            ->with(['owner'])
            ->withCount('tokens')
            ->when($this->search !== '', fn (Builder $q): Builder => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->partyFilter === 'first_party', fn (Builder $q): Builder => $q->whereNull('owner_id'))
            ->when($this->partyFilter === 'third_party', fn (Builder $q): Builder => $q->whereNotNull('owner_id'))
            ->when($this->statusFilter === 'active', fn (Builder $q): Builder => $q->where('revoked', false))
            ->when($this->statusFilter === 'revoked', fn (Builder $q): Builder => $q->where('revoked', true))->latest()
            ->paginate(20);
    }

    public function showAuditLog(string $clientId): void
    {
        $this->clientForAudit = $clientId;
        $this->dispatch('open-oauth-client-audit-modal');
    }

    public function closeAuditLog(): void
    {
        $this->clientForAudit = null;
        $this->dispatch('close-oauth-client-audit-modal');
    }

    /**
     * @return Collection<int, OAuthClientEvent>
     */
    public function getAuditTrailProperty(): Collection
    {
        if ($this->clientForAudit === null) {
            return new Collection;
        }

        return OAuthClientEvent::query()
            ->with('actor')
            ->where('client_id', $this->clientForAudit)
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    public function confirmRevocation(string $clientId): void
    {
        $this->confirmingRevocationFor = $clientId;
        $this->dispatch('open-oauth-client-revoke-modal');
    }

    public function revokeClient(): void
    {
        if ($this->confirmingRevocationFor === null) {
            return;
        }

        $client = Client::query()->whereKey($this->confirmingRevocationFor)->firstOrFail();
        $client->forceFill(['revoked' => true])->save();

        /** @var ?User $actor */
        $actor = auth()->user() instanceof User ? auth()->user() : null;

        // Revoke every outstanding access + refresh token issued by this client so the action takes effect immediately.
        Passport::token()->newQuery()->where('client_id', $client->getKey())->update(['revoked' => true]);
        Passport::refreshToken()->newQuery()
            ->whereIn('access_token_id', Passport::token()->newQuery()->where('client_id', $client->getKey())->pluck('id'))
            ->update(['revoked' => true]);

        resolve(OAuthClientEventRecorderService::class)->record(
            event: OAuthClientEventType::ADMIN_REVOKED,
            client: $client,
            actor: $actor,
        );

        Flux::toast(heading: __('Client revoked'), text: __('The OAuth client and every active token it issued have been revoked.'), variant: 'success');
        $this->confirmingRevocationFor = null;
        $this->dispatch('close-oauth-client-revoke-modal');
    }
};
