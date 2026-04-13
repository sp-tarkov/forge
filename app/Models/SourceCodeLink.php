<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SourceCodeLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Override;

/**
 * @property int $id
 * @property string $sourceable_type
 * @property int $sourceable_id
 * @property string $url
 * @property string|null $label
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Model $sourceable
 */
#[Table(name: 'source_code_links')]
final class SourceCodeLink extends Model
{
    /** @use HasFactory<SourceCodeLinkFactory> */
    use HasFactory;

    /**
     * The polymorphic relationship to the parent model (Mod or Addon).
     *
     * @return MorphTo<Model, $this>
     */
    public function sourceable(): MorphTo
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
            'sourceable_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
