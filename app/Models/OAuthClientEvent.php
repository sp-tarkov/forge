<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OAuthClientEventType;
use Carbon\CarbonImmutable;
use Database\Factories\OAuthClientEventFactory;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Passport\Client;
use Override;

/**
 * @property int $id
 * @property string|null $client_id
 * @property int|null $actor_user_id
 * @property OAuthClientEventType $event
 * @property string|null $ip
 * @property string|null $user_agent
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable $created_at
 * @property-read User|null $actor
 * @property-read Client|null $client
 */
#[Table(name: 'oauth_client_events')]
#[WithoutTimestamps]
final class OAuthClientEvent extends Model
{
    /** @use HasFactory<OAuthClientEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * Actor (user who triggered the event). Nullable because admin actions taken via a system context may have no
     * actor, and because we null the FK on user deletion to keep historical events legible.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * Underlying OAuth client. Nullable because we keep the event around after the client has been deleted.
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'event' => OAuthClientEventType::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
