<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\PublishedScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * Mod Addon Version
 */
#[ScopedBy([PublishedScope::class])]
class ModAddonVersion extends Model
{
    //
}
