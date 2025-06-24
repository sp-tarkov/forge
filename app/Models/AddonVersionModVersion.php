<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * AddonVersionModVersion Pivot Model
 *
 * @property int $id
 * @property int $addon_version_id
 * @property int $mod_version_id
 * @property-read AddonVersion $addonVersion
 * @property-read ModVersion $modVersion
 */
class AddonVersionModVersion extends Pivot
{
    public $incrementing = true;
}
