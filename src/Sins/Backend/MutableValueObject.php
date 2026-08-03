<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\ValueObjects;

final class MutableValueObject extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'mutable-value-object',
            skill: ValueObjects::class,
            description: "a value type that writes its own field after construction — two holders of the same value, and one of them can change it under the other",
            rule: "Make a value immutable: build it complete and derive a NEW one to change it; never write its fields after construction.",
            suggestion: "`readonly` on the class, and a `with…()`/named derivation that returns a new instance (PHP 8.5's `clone with`).",
        );
    }
}
