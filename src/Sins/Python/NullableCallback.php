<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Absence;

final class NullableCallback extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-nullable-callback',
            skill: Absence::class,
            description: '`cb: Callable | None = None` asked `if cb is not None:` / `if cb:` / `cb or …` in the body — a no-op wearing a disguise',
            rule: 'Default an optional callback to a no-op in the signature; don\'t take `None` and normalise it in the body.',
            suggestion: 'Default the parameter to a named no-op — `def ignore(*_): pass`, then `on_retry: Callable[[int], None] = ignore` — and call it unconditionally.',
        );
    }
}
