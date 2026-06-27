<?php

declare(strict_types=1);

use App\Models\SptVersion;
use App\Models\User;
use App\Support\Api\V0\PublicViewpoint;
use Illuminate\Support\Facades\Date;

describe('PublishedSptVersionScope', function (): void {
    beforeEach(function (): void {
        $this->admin = User::factory()->admin()->create();
    });

    it('filters out unpublished SPT versions for guests', function (): void {
        $published = SptVersion::factory()->create(['version' => '1.0.0', 'publish_date' => Date::now()->subDay()]);
        $unpublished = SptVersion::factory()->create(['version' => '1.0.1', 'publish_date' => null]);
        $future = SptVersion::factory()->create(['version' => '1.0.2', 'publish_date' => Date::now()->addDay()]);

        $results = SptVersion::query()->get();

        expect($results->pluck('id'))->toContain($published->id)
            ->not->toContain($unpublished->id)
            ->not->toContain($future->id);
    });

    it('allows admins to see all SPT versions', function (): void {
        $published = SptVersion::factory()->create(['version' => '1.0.0', 'publish_date' => Date::now()->subDay()]);
        $unpublished = SptVersion::factory()->create(['version' => '1.0.1', 'publish_date' => null]);

        $this->actingAs($this->admin);
        $results = SptVersion::query()->get();

        expect($results->pluck('id'))->toContain($published->id)
            ->toContain($unpublished->id);
    });

    it('hides unpublished SPT versions even from admins when the public viewpoint is forced', function (): void {
        $published = SptVersion::factory()->create(['version' => '1.0.0', 'publish_date' => Date::now()->subDay()]);
        $unpublished = SptVersion::factory()->create(['version' => '1.0.1', 'publish_date' => null]);

        $this->actingAs($this->admin);
        PublicViewpoint::force(request());

        $results = SptVersion::query()->get();

        expect($results->pluck('id'))->toContain($published->id)
            ->not->toContain($unpublished->id);
    });
});
