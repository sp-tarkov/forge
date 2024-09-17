<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class License extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The relationship between a license and mod.
     *
     * @return HasMany<Mod>
     */
    public function mods(): HasMany
    {
        return $this->hasMany(Mod::class)
            ->chaperone();
    }
}
