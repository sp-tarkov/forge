<?php

declare(strict_types=1);

namespace App\Support\DataTransferObjects\Concerns;

/**
 * Coercion helpers for rebuilding data-transfer objects from decoded JSON arrays, where every value is `mixed`.
 */
trait CoercesArrayValues
{
    private static function coerceInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function coerceString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function coerceNullableString(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    private static function coerceBool(mixed $value): bool
    {
        return (bool) $value;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function coerceArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @return list<string>
     */
    private static function coerceStringList(mixed $value): array
    {
        $list = [];
        foreach (self::coerceArray($value) as $item) {
            if (is_scalar($item)) {
                $list[] = (string) $item;
            }
        }

        return $list;
    }
}
