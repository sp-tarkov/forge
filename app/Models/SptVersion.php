<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SptVersion extends Model
{
    use HasFactory, SoftDeletes;

    public function modVersions(): HasMany
    {
        return $this->hasMany(ModVersion::class);
    }
}
