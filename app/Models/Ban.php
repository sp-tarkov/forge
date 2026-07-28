<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\BanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Mchev\Banhammer\Models\Ban as BaseBan;

/**
 * @property int $id
 * @property string|null $bannable_type
 * @property int|null $bannable_id
 * @property string|null $created_by_type
 * @property int|null $created_by_id
 * @property string|null $comment
 * @property string|null $ip
 * @property CarbonImmutable|null $expired_at
 * @property array<string, mixed>|null $metas
 * @property CarbonImmutable|null $deleted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class Ban extends BaseBan
{
    /** @use HasFactory<BanFactory> */
    use HasFactory;
}
