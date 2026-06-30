<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Absence;

final class DeNulledFinder extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'de-nulled-finder',
            skill: Absence::class,
            description: "Missing = broken state returned as `?T`/null instead of throwing (a `?T` finder whose callers de-null it)",
            rule: "Decide absence at the source — a finder whose callers all de-null it should return a total type (throw/Option/empty), not a travelling `?T`.",
            suggestion: "Add a resolve-or-throw `get()` beside `find()`, or return `Option<T>`."
        );
    }
}
