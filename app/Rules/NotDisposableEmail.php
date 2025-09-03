<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\DisposableEmailBlocklist;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class NotDisposableEmail implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=):PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        // Extract domain from email
        $parts = explode('@', $value);

        if (count($parts) !== 2) {
            return;
        }

        $domain = strtolower($parts[1]);

        if (DisposableEmailBlocklist::isDisposable($domain)) {
            $fail('This email address has been detected as disposable and is not supported.');
        }
    }
}
