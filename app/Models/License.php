<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class License extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The relationship between a license and mod.
     */
    public function mods(): HasMany
    {
        return $this->hasMany(Mod::class)
            ->chaperone();
    }
}
