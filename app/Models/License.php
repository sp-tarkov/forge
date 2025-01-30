<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * License Model
 *
 * @property int $id
 * @property int|null $hub_id
 * @property string $name
 * @property string $link
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Mod> $mods
 */
class License extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The relationship between a license and mod.
     *
     * @return HasMany<Mod, $this>
     */
    public function mods(): HasMany
    {
        return $this->hasMany(Mod::class)
            ->chaperone();
    }

    /**
     * The attributes that should be cast to native types.
     */
    protected function casts(): array
    {
        return [
            'hub_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
