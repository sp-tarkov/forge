<?php

declare(strict_types=1);

use App\Rules\SemverConstraint;

/**
 * Run the SemverConstraint rule and return the failure message, or null when validation passed.
 */
function runSemverConstraintRule(string $value): ?string
{
    $rule = new SemverConstraint;
    $message = null;
    $rule->validate('constraint', $value, function (string $msg) use (&$message): void {
        $message = $msg;
    });

    return $message;
}

describe('SemverConstraint rule', function (): void {
    it('accepts valid version constraints', function (string $constraint): void {
        expect(runSemverConstraintRule($constraint))->toBeNull();
    })->with(['~4.4.3', '^2.0.0', '>=3.0.0 <4.0.0', '1.0.0', '^2.0 || >=3.1']);

    it('rejects invalid version constraints', function (string $constraint): void {
        expect(runSemverConstraintRule($constraint))->toContain('valid semantic version constraint');
    })->with(['not-a-constraint', 'abc def', '~~1']);
});
