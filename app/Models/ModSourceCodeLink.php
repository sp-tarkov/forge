<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ModSourceCodeLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $mod_id
 * @property string $url
 * @property string $label
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Mod $mod
 */
class ModSourceCodeLink extends Model
{
    /** @use HasFactory<ModSourceCodeLinkFactory> */
    use HasFactory;

    /**
     * The relationship between a source code link and its mod.
     *
     * @return BelongsTo<Mod, $this>
     */
    public function mod(): BelongsTo
    {
        return $this->belongsTo(Mod::class);
    }

    /**
     * The attributes that should be cast to native types.
     */
    protected function casts(): array
    {
        return [
            'mod_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
