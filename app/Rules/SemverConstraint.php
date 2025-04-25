<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Composer\Semver\Semver;
use Exception;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class SemverConstraint implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=):PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            // Attempt to parse the version constraint using the Semver library.
            Semver::satisfiedBy([], $value);
        } catch (Exception $exception) {
            $fail($exception->getMessage());
        }
    }
}
