<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\ValueObjects;

final class DictReturnBag extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-dict-return-bag',
            skill: ValueObjects::class,
            description: '`return {"total": …, "tax": …}` — a record of several fields handed back as a dict its callers read by string key',
            rule: 'Return a typed value — a frozen dataclass — not a dict of several named fields.',
            suggestion: 'A `@dataclass(frozen=True)` for the result, built where it is returned — or a `TypedDict` when a dict must cross a boundary.',
        );
    }
}
