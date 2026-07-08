<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\RepeatedCallHelper;

final class RepeatedNamedCall extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'repeated-named-call',
            skill: RepeatedCallHelper::class,
            description: "The same `with`-style (variadic) method is called with the same named argument at 2+ sites, instead of a named helper on the type",
            rule: "Promote a repeated `->with…(named: …)` call into a method on the receiver's type that hides the call and its construction boilerplate.",
            suggestion: "`\$element->withMetadata(\$payload)` — a `withMetadata()` on the type doing `copyWith(metadata: \$payload->toArray())`."
        );
    }
}
