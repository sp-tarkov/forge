<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\VirusTotalLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Override;

/**
 * @property int $id
 * @property string $linkable_type
 * @property int $linkable_id
 * @property string $url
 * @property string|null $label
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Model $linkable
 */
#[Table(name: 'virus_total_links')]
final class VirusTotalLink extends Model
{
    /** @use HasFactory<VirusTotalLinkFactory> */
    use HasFactory;

    /**
     * The polymorphic relationship to the parent model (ModVersion or AddonVersion).
     *
     * @return MorphTo<Model, $this>
     */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The attributes that should be cast to native types.
     *
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'linkable_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
