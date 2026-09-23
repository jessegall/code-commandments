<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\RoleVocabulary;

final class NullableRegistryLookup extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-nullable-registry-lookup',
            skill: RoleVocabulary::class,
            description: 'a keyed store handing back `None` for a key it lacks — `return self._handlers.get(kind)` — so every caller decides what a miss means',
            rule: 'A store\'s lookup returns the item or raises a named exception; where a miss is genuinely expected, callers ask `key in store` first.',
            suggestion: 'Index the dict and turn the `KeyError` into a named exception (`raise UnknownHandler.for_kind(kind) from missing`), and give the class a `__contains__` for the callers that expect misses.',
        );
    }
}
