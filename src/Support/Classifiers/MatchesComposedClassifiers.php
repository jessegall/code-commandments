<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Classifiers;

/**
 * The body of a COMPOUND classifier — holds the composed classifiers and matches
 * by delegating to each with ALL/ANY logic. Used by the anonymous compound a base
 * builds in {@see ComposesClassifiers::composeClassifiers()}.
 *
 * `matches()` is variadic so this one trait serves every base regardless of its
 * own `matches()` signature (a TypeClassifier's `(string, ?CodebaseIndex)`, an
 * InstanceClassifier's `(string|object)`) — the arguments are passed straight
 * through to the children.
 */
trait MatchesComposedClassifiers
{
    /**
     * @param  list<Classifier>  $classifiers
     */
    public function __construct(
        private readonly array $classifiers,
        private readonly bool $requireAll,
    ) {}

    public function matches(mixed ...$arguments): bool
    {
        foreach ($this->classifiers as $classifier) {
            $matched = $classifier->matches(...$arguments);

            if ($this->requireAll && ! $matched) {
                return false;
            }

            if (! $this->requireAll && $matched) {
                return true;
            }
        }

        return $this->requireAll && $this->classifiers !== [];
    }
}
