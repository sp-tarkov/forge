<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\UserRoleFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * UserRole Model
 *
 * @property int $id
 * @property string $name
 * @property string $short_name
 * @property string $description
 * @property string $color_class
 * @property string $icon
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Collection<int, User> $users
 */
final class UserRole extends Model
{
    /** @use HasFactory<UserRoleFactory> */
    use HasFactory;

    /**
     * The relationship between a user role and users.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
