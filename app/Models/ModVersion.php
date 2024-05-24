<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ModVersion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'mod_id',
        'version',
        'description',
        'spt_version_id',
        'virus_total_link',
        'downloads',
    ];

    public function mod(): BelongsTo
    {
        return $this->belongsTo(Mod::class);
    }

    public function sptVersion(): BelongsTo
    {
        return $this->belongsTo(SptVersion::class);
    }
}
