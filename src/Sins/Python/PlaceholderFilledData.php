<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\TypeHonesty;

final class PlaceholderFilledData extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-placeholder-filled-data',
            skill: TypeHonesty::class,
            description: '`Card(title=…, body="")` — a dataclass field required as `str` handed the blank to satisfy the signature, a value the type cannot catch',
            rule: 'A required field means the caller has the value; never fill one with `""` to satisfy the signature.',
            suggestion: 'Fetch the real value — or split a narrower dataclass that only promises what this caller knows.',
        );
    }
}
