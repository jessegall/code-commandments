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
                purpose: 'Composable chain resolver — Resolver::using($strategy, ...entries).',
                subNamespace: 'Resolvers',
            ),
            new Scaffold(
                name: 'resolve-strategy',
                className: 'ResolveStrategy',
                stubFile: 'ResolveStrategy.stub',
                introducedIn: '1.63.0',
                purpose: 'How a resolver combines its chain results (the compose handler).',
                subNamespace: 'Resolvers\\Strategies',
            ),
            new Scaffold(
                name: 'strategy-first-result-wins',
                className: 'FirstResultWins',
                stubFile: 'FirstResultWins.stub',
                introducedIn: '1.63.0',
                purpose: 'ResolveStrategy: first non-null result wins.',
                subNamespace: 'Resolvers\\Strategies',
            ),
            new Scaffold(
                name: 'strategy-collect-results',
                className: 'CollectResults',
                stubFile: 'CollectResults.stub',
                introducedIn: '1.63.0',
                purpose: 'ResolveStrategy: collect every non-null result into a list.',
                subNamespace: 'Resolvers\\Strategies',
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
            new Scaffold(
                name: 'predicate-has-prefix',
                className: 'HasPrefix',
                stubFile: 'HasPrefix.stub',
                introducedIn: '1.63.0',
                purpose: 'Predicate: value is a string with a given prefix (HasPrefix::of(...)).',
                subNamespace: 'Resolvers\\Predicates',
            ),
            new Scaffold(
                name: 'predicate-equals',
                className: 'Equals',
                stubFile: 'Equals.stub',
                introducedIn: '1.63.0',
                purpose: 'Predicate: value === expected (Equals::to(...)).',
                subNamespace: 'Resolvers\\Predicates',
            ),
            new Scaffold(
                name: 'predicate-has-class',
                className: 'HasClass',
                stubFile: 'HasClass.stub',
                introducedIn: '1.67.0',
                purpose: 'Predicate: value is an instance of a class/interface (HasClass::of(...)).',
                subNamespace: 'Resolvers\\Predicates',
            ),
            new Scaffold(
                name: 'predicate-entry',
                className: 'PredicateEntry',
                stubFile: 'PredicateEntry.stub',
                introducedIn: '1.66.0',
                purpose: 'A predicate + a matched-value transform; Predicate::transform()->when().',
                subNamespace: 'Resolvers\\Predicates',
            ),
            new Scaffold(
                name: 'transform',
                className: 'Transform',
                stubFile: 'Transform.stub',
                introducedIn: '1.66.0',
                purpose: 'Base for a matched-value transform (Predicate::transform()).',
                subNamespace: 'Resolvers\\Transforms',
            ),
            new Scaffold(
                name: 'transform-strip-prefix',
                className: 'StripPrefix',
                stubFile: 'StripPrefix.stub',
                introducedIn: '1.66.0',
                purpose: 'Transform: drop a known prefix (StripPrefix::of(...)).',
                subNamespace: 'Resolvers\\Transforms',
            ),
            new Scaffold(
                name: 'factory-capture',
                className: 'Capture',
                stubFile: 'Capture.stub',
                introducedIn: '1.70.0',
                purpose: 'Result factory: return the matched value unchanged (Capture::make()).',
                subNamespace: 'Resolvers\\Factories',
            ),
            new Scaffold(
                name: 'factory-wrap',
                className: 'Wrap',
                stubFile: 'Wrap.stub',
                introducedIn: '1.70.0',
                purpose: 'Result factory: wrap the matched value in a single-element list (Wrap::make()).',
                subNamespace: 'Resolvers\\Factories',
            ),
        ];
    }
}
