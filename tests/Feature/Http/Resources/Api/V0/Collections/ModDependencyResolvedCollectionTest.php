<?php

declare(strict_types=1);

use App\Http\Resources\Api\V0\Collections\ModDependencyResolvedCollection;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

it('skips a resolved dependency whose mod is hidden instead of reading id on null', function (): void {
    SptVersion::factory()->state(['version' => '3.8.0'])->create();

    $mod = Mod::factory()->create();
    $version = ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '3.8.0']);

    // Mirror what the public-visibility scope produces: the dependency version is present, but its parent mod has been
    // filtered out, so the eager-loaded mod relation resolves to null. Previously this read id on null and 500'd the
    // whole response.
    $version->setRelation('mod', null);

    $collection = new ModDependencyResolvedCollection(new EloquentCollection([$version]));

    expect($collection->toArray(request()))->toBe([]);
});
