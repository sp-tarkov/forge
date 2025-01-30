<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OAuthConnection Model
 *
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $provider_id
 * @property string $token
 * @property string $refresh_token
 * @property string $nickname
 * @property string $name
 * @property string $email
 * @property string $avatar
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 */
class OAuthConnection extends Model
{
    use HasFactory;

    protected $table = 'oauth_connections';

    /**
     * The relationship between the OAuth connection and the user.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The attributes that should be cast to native types.
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
