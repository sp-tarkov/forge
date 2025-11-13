<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class DoesNotContainLogFile implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $logFilePattern = '/(?:\[(?:Message|Info|Warning|Error)\s*:\s+[^\]]+\]|\[\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\.\d{3}\]\[(?:Info|Debug|Warning|Error)\]\[|\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\.\d{3}\s+[+\-]\d{2}:\d{2}\|\d+\.\d+\.\d+\.\d+\.\d+\||"_(?:id|tpl)":\s*"[0-9a-f]{24}")/';

        if (preg_match($logFilePattern, $value)) {
            $fail('Log files detected! Please use our code paste service instead: https://codepaste.sp-tarkov.com');
        }
    }
}
