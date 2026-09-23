<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\TypeHonesty;

final class ConstantProperty extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-constant-property',
            skill: TypeHonesty::class,
            description: 'an `@property` whose body never reads `self` — `return "box"` — a stored value dressed as a computed one',
            rule: 'A `@property` must derive from the object; a value it never reads `self` for is a class attribute.',
            suggestion: '`kind = "box"` on the class — or a `ClassVar` — and the property goes.',
        );
    }
}
