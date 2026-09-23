<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\ValueObjects;

final class MutableValueObject extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-mutable-value-object',
            skill: ValueObjects::class,
            description: 'a dataclass whose own methods write the fields it was built from after construction — a value that changes under everyone holding it',
            rule: 'Make a value immutable: build it complete and derive a new one to change it; never write its fields after construction.',
            suggestion: '`@dataclass(frozen=True)` and `return replace(self, amount=…)` from the method that changed it.',
        );
    }
}
