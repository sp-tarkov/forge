<?php

declare(strict_types=1);

namespace App\Support\Api\V0\QueryBuilder;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Override;

/**
 * @extends AbstractQueryBuilder<User>
 */
class UserQueryBuilder extends AbstractQueryBuilder
{
    /**
     * @return array<string, string>
     */
    public static function getAllowedFilters(): array
    {
        return [
            'id' => 'filterById',
        ];
    }

    /**
     * @return list<string>
     */
    public static function getAllowedIncludes(): array
    {
        return [
            'role',
            // TODO: Implement the following options:
            // 'ownedMods',
            // 'authoredMods',
            // 'followers',
            // 'following',
        ];
    }

    /**
     * @return list<string>
     */
    public static function getAllowedFields(): array
    {
        return [
            'id',
            'name',
            'email',
            'email_verified_at',
            'user_role_id',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * @return list<string>
     */
    public static function getAllowedSorts(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public static function getRequiredFields(): array
    {
        return ['id'];
    }

    /**
     * @return array<string, array<string>>
     */
    #[Override]
    protected static function getDynamicAttributes(): array
    {
        return [
            'profile_photo_url' => ['profile_photo_path'],
            'cover_photo_url' => ['cover_photo_path'],
        ];
    }

    /**
     * @return Builder<User>
     */
    protected function getBaseQuery(): Builder
    {
        return User::query()->select('users.*');
    }

    /**
     * @return class-string<User>
     */
    protected function getModelClass(): string
    {
        return User::class;
    }

    /**
     * @param  Builder<User>  $query
     */
    protected function filterById(Builder $query, mixed $value): void
    {
        $query->where('id', $value);
    }
}
