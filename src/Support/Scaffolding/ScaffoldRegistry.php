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
                name: 'equality-operator',
                className: 'EqualityOperator',
                stubFile: 'EqualityOperator.stub',
                introducedIn: '1.52.0',
                purpose: 'Typed equality operators the CompareSelf trait forwards to.',
            ),
            new Scaffold(
                name: 'compare-self',
                className: 'CompareSelf',
                stubFile: 'CompareSelf.stub',
                introducedIn: '1.49.0',
                purpose: 'Enum trait: $case->equals($x) / Enum::equals($x, Case) (null-safe) for SuggestCompareSelfTrait.',
            ),
            new Scaffold(
                name: 'resolver',
                className: 'Resolver',
                stubFile: 'Resolver.stub',
                introducedIn: '1.59.0',
                purpose: 'Chain resolver base — first non-null result wins.',
                subNamespace: 'Resolvers',
            ),
            new Scaffold(
                name: 'predicate',
                className: 'Predicate',
                stubFile: 'Predicate.stub',
                introducedIn: '1.59.0',
                purpose: 'Composable boolean-test base with and()/or()/not().',
                subNamespace: 'Resolvers\\Predicates',
            ),
            new Scaffold(
                name: 'predicate-all-of',
                className: 'AllOf',
                stubFile: 'AllOf.stub',
                introducedIn: '1.59.0',
                purpose: 'Predicate combinator: logical AND.',
                subNamespace: 'Resolvers\\Predicates',
            ),
            new Scaffold(
                name: 'predicate-any-of',
                className: 'AnyOf',
                stubFile: 'AnyOf.stub',
                introducedIn: '1.59.0',
                purpose: 'Predicate combinator: logical OR.',
                subNamespace: 'Resolvers\\Predicates',
            ),
            new Scaffold(
                name: 'predicate-negated',
                className: 'Negated',
                stubFile: 'Negated.stub',
                introducedIn: '1.59.0',
                purpose: 'Predicate combinator: logical NOT.',
                subNamespace: 'Resolvers\\Predicates',
            ),
            new Scaffold(
                name: 'predicate-is-null',
                className: 'IsNull',
                stubFile: 'IsNull.stub',
                introducedIn: '1.59.0',
                purpose: 'Predicate: value is null.',
                subNamespace: 'Resolvers\\Predicates',
            ),
            new Scaffold(
                name: 'predicate-is-enum',
                className: 'IsEnum',
                stubFile: 'IsEnum.stub',
                introducedIn: '1.59.0',
                purpose: 'Predicate: value is a case of a given enum (IsEnum::for(Enum::class)).',
                subNamespace: 'Resolvers\\Predicates',
            ),
        ];
    }
}
