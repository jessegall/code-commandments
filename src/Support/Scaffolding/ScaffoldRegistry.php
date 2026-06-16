<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Scaffolding;

/**
 * The catalogue of support classes the package can generate. Add a Scaffold
 * here (and a matching stub in stubs/scaffold/) and every consumer is offered
 * it on their next sync.
 */
final class ScaffoldRegistry
{
    /**
     * @return list<Scaffold>
     */
    public static function all(): array
    {
        return [
            new Scaffold(
                name: 'from-array-only',
                className: 'FromArrayOnly',
                stubFile: 'FromArrayOnly.stub',
                introducedIn: '1.39.0',
                purpose: 'Trait that forces Spatie Data ::from() to take an array (no magic dispatch).',
            ),
            new Scaffold(
                name: 'option',
                className: 'Option',
                stubFile: 'Option.stub',
                introducedIn: '1.39.0',
                purpose: 'Present-or-absent value type for PreferOptionOverNull.',
            ),
            new Scaffold(
                name: 'null-callable',
                className: 'NullCallable',
                stubFile: 'NullCallable.stub',
                introducedIn: '1.39.0',
                purpose: 'Null Object for a callable/Closure slot.',
            ),
            new Scaffold(
                name: 'compare-self',
                className: 'CompareSelf',
                stubFile: 'CompareSelf.stub',
                introducedIn: '1.49.0',
                purpose: 'Enum trait: $case->equals($x) / Enum::equals($x, Case) (null-safe) for SuggestCompareSelfTrait.',
            ),
        ];
    }
}
