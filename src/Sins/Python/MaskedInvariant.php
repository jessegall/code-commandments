<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\TypeHonesty;

final class MaskedInvariant extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-masked-invariant',
            skill: TypeHonesty::class,
            description: 'a literal answering for the object\'s own scratch state — `self.period.includes(day) if self.period else False` — where the field is only unset because an operation sets it part-way',
            rule: 'Make the invariant certain instead of masking it: pass the per-call value as a parameter, or hold it non-optional from construction.',
            suggestion: 'Hand the value to the methods that need it (`covers(period, day)`) or build a per-call object holding it, and delete the fallback.',
        );
    }
}
