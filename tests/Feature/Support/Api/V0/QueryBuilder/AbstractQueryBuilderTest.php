<?php

declare(strict_types=1);

use App\Models\SptVersion;

describe('date range filter', function (): void {
    beforeEach(function (): void {
        $this->first = SptVersion::factory()->create(['version' => '9.1.0', 'created_at' => '2021-01-01 00:00:00']);
        $this->second = SptVersion::factory()->create(['version' => '9.2.0', 'created_at' => '2021-01-02 00:00:00']);
        $this->third = SptVersion::factory()->create(['version' => '9.3.0', 'created_at' => '2021-01-03 12:30:00']);
    });

    it('includes records that fall exactly on the end bound', function (): void {
        $response = $this->getJson('/api/v0/spt/versions?filter[created_between]=2021-01-01,2021-01-02');

        $response->assertOk();

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        expect($returnedIds)
            ->toContain($this->first->id)
            ->toContain($this->second->id)
            ->not->toContain($this->third->id);
    });

    it('honours a time component on the bounds', function (): void {
        $response = $this->getJson('/api/v0/spt/versions?filter[created_between]=2021-01-03 00:00:00,2021-01-03 13:00:00');

        $response->assertOk();

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        expect($returnedIds)
            ->toContain($this->third->id)
            ->not->toContain($this->first->id)
            ->not->toContain($this->second->id);
    });

    it('ignores a range that is not two values', function (string $range): void {
        $response = $this->getJson('/api/v0/spt/versions?filter[created_between]='.$range);

        $response->assertOk()->assertJsonCount(3, 'data');
    })->with([
        'single value' => '2021-01-01',
        'three values' => '2021-01-01,2021-01-02,2021-01-03',
        'empty string' => '',
    ]);

    it('ignores a range that cannot be parsed as dates', function (): void {
        $response = $this->getJson('/api/v0/spt/versions?filter[created_between]=not-a-date,also-not-a-date');

        $response->assertOk()->assertJsonCount(3, 'data');
    });
});
