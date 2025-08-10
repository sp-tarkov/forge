<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * ModVersionSptVersion Pivot Model
 *
 * @property int $id
 * @property int $mod_version_id
 * @property int $spt_version_id
 * @property-read ModVersion $modVersion
 * @property-read SptVersion $sptVersion
 */
class ModVersionSptVersion extends Pivot
{
    use HasFactory;
    public $incrementing = true;
}
