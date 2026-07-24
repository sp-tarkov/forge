<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates that the user ID under validation has no block relationship with the given user, in either direction.
 * Exempt user IDs are skipped so existing associations remain valid after a later block.
 */
final readonly class NoBlockRelationship implements ValidationRule
{
    /**
     * @param  list<int>  $exemptUserIds
     */
    public function __construct(
        private ?User $user,
        private array $exemptUserIds = []
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->user instanceof User || ! is_numeric($value)) {
            return;
        }

        $userId = (int) $value;

        if ($userId === $this->user->id || in_array($userId, $this->exemptUserIds, true)) {
            return;
        }

        if ($this->user->hasBlocked($userId) || $this->user->isBlockedBy($userId)) {
            $name = User::query()->find($userId)->name ?? 'This user';
            $fail(__(':name cannot be added as an author.', ['name' => $name]));
        }
    }
}
