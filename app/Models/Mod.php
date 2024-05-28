<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Mod extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'license_id',
        'source_code_link',
    ];

    protected function slug(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => strtolower($value),
            set: fn (string $value) => Str::slug($value),
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ModVersion::class);
    }

    public function scopeWithTotalDownloads($query)
    {
        $query->addSelect(['total_downloads' => ModVersion::selectRaw('SUM(downloads) AS total_downloads')
            ->whereColumn('mod_id', 'mods.id'),
        ]);
    }

    public function latestSptVersion(): BelongsTo
    {
        return $this->belongsTo(ModVersion::class, 'latest_spt_version_id');
    }

    public function scopeWithLatestSptVersion($query)
    {
        return $query
            ->addSelect(['latest_spt_version_id' => ModVersion::select('id')
                ->whereColumn('mod_id', 'mods.id')
                ->orderByDesc(
                    SptVersion::select('version')
                        ->whereColumn('mod_versions.spt_version_id', 'spt_versions.id')
                        ->orderByDesc(DB::raw('naturalSort(version)'))
                        ->take(1),
                )
                ->orderByDesc(DB::raw('naturalSort(version)'))
                ->take(1),
            ])
            ->with(['latestSptVersion', 'latestSptVersion.sptVersion']);
    }

    public function lastUpdatedVersion(): BelongsTo
    {
        return $this->belongsTo(ModVersion::class, 'last_updated_spt_version_id');
    }

    public function scopeWithLastUpdatedVersion($query)
    {
        return $query
            ->addSelect(['last_updated_spt_version_id' => ModVersion::select('id')
                ->whereColumn('mod_id', 'mods.id')
                ->orderByDesc('updated_at')
                ->take(1),
            ])
            ->orderByDesc(
                ModVersion::select('updated_at')
                    ->whereColumn('mod_id', 'mods.id')
                    ->orderByDesc('updated_at')
                    ->take(1)
            )
            ->with(['lastUpdatedVersion', 'lastUpdatedVersion.sptVersion']);
    }
}
