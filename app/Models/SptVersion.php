<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SptVersion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'version',
        'color_class',
    ];

    public function mod_versions(): HasMany
    {
        return $this->hasMany(ModVersion::class);
    }
}
