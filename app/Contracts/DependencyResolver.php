<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\AddonVersion;
use App\Models\ModVersion;

interface DependencyResolver
{
    public function resolve(ModVersion|AddonVersion $dependable): void;
}
