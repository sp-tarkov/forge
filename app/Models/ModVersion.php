<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    public function sptVersion(): belongsTo
    {
        return $this->belongsTo(SptVersion::class);
    }

    public function scopeLastUpdated(Builder $query): void
    {
        $query->orderByDesc('created_at');
    }

    public function scopeLatestSptVersion(Builder $query): void
    {
        $query->orderByDesc(
            SptVersion::select('spt_versions.version')->whereColumn('mod_versions.spt_version_id', 'spt_versions.id')
        )->orderByDesc('mod_versions.version');
    }
}
