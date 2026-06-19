<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\VersionMatcher;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

final class SemverConstraint implements ValidationRule
{
    /**
     * Run the validation rule to ensure the value is a valid semantic version constraint.
     *
     * @param  Closure(string, ?string=):PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        /** @var string $value */
        if (! VersionMatcher::isValidConstraint($value)) {
            $fail(__('Please enter a valid semantic version constraint.'));
        }
    }
}
