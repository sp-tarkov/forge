<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * AddonVersionModVersion Pivot Model
 *
 * @property int $id
 * @property int $addon_version_id
 * @property int $mod_version_id
 * @property-read ModAddonVersion $addonVersion
 * @property-read ModVersion $modVersion
 */
class ModAddonVersionModVersion extends Pivot
{
    public $incrementing = true;
}
