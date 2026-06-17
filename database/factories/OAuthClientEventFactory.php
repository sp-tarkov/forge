<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OAuthClientEventType;
use App\Models\OAuthClientEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OAuthClientEvent>
 */
final class OAuthClientEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => (string) Str::uuid(),
            'actor_user_id' => null,
            'event' => OAuthClientEventType::CREATED,
            'ip' => '127.0.0.1',
            'user_agent' => 'Test/1.0',
            'metadata' => null,
        ];
    }
}
