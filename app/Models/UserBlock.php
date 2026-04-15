<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\UserBlockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $blocker_id
 * @property int $blocked_id
 * @property string|null $reason
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $blocker
 * @property-read User $blocked
 */
final class UserBlock extends Model
{
    /** @use HasFactory<UserBlockFactory> */
    use HasFactory;

    /**
     * Get the user who created the block
     *
     * @return BelongsTo<User, $this>
     */
    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocker_id');
    }

    /**
     * Get the user who is blocked
     *
     * @return BelongsTo<User, $this>
     */
    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }
}
