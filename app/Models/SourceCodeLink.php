<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SourceCodeLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $sourceable_type
 * @property int $sourceable_id
 * @property string $url
 * @property string|null $label
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Model $sourceable
 */
class SourceCodeLink extends Model
{
    /** @use HasFactory<SourceCodeLinkFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'source_code_links';

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
    protected function casts(): array
    {
        return [
            'sourceable_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
