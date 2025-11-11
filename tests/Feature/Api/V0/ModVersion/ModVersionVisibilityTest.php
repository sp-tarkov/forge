<?php

declare(strict_types=1);

use App\Enums\Api\V0\ApiErrorCode;
use App\Models\Mod;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

describe('Mod Version Visibility', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->token = $this->user->createToken('test-token')->plainTextToken;
    });

    it('returns not found when the mod has no published versions', function (): void {
        $mod = Mod::factory()->create();

        $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%d/versions', $mod->id));

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('code', ApiErrorCode::NOT_FOUND->value);
    });
});
