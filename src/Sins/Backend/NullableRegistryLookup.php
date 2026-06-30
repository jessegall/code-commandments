<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\RoleVocabulary;

final class NullableRegistryLookup extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'nullable-registry-lookup',
            skill: RoleVocabulary::class,
            description: "A keyed-store `get()` that returns `null` on a miss (should resolve-or-throw)",
            rule: "A keyed store's `get()` resolves-or-throws on a miss; don't return `null`.",
            suggestion: "`get()` returns-or-throws a named `…NotFound::forKey(\$key)`."
        );
    }
}
