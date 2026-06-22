<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Api\V0\ApiUsagePeriod;
use Carbon\CarbonImmutable;
use Database\Factories\ApiUsageClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * @property int $id
 * @property ApiUsagePeriod $period
 * @property CarbonImmutable $period_start
 * @property string $ip
 * @property int $request_count
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class ApiUsageClient extends Model
{
    /** @use HasFactory<ApiUsageClientFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'period' => ApiUsagePeriod::class,
            'period_start' => 'datetime',
            'request_count' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
