<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('indexes mod_versions for fika_compatibility lookups', function (): void {
    expect(Schema::hasIndex('mod_versions', ['mod_id', 'fika_compatibility', 'published_at']))->toBeTrue();
});
