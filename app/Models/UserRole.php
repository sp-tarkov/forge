<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
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
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, User> $users
 */
class UserRole extends Model
{
    use HasFactory;

    /**
     * The relationship between a user role and users.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class)
            ->chaperone();
    }
}
