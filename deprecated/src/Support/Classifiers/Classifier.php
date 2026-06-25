<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Classifiers;

/**
 * The root of the classifier family — a thing that decides whether a subject
 * belongs to a KIND. The concrete bases ({@see TypeClassifier}, index-backed)
 * define HOW they decide (their {@see matches()} signature) and gain boolean
 * composition from {@see ComposesClassifiers}; this base is the shared type.
 *
 * `matches()` stays an instance method (the `allOf`/`anyOf` compounds carry
 * per-instance state, so it cannot be static); {@see make()} is the static
 * entry point for one-offs and composition — `CollectionClassifier::make()
 * ->matches($fqcn)`, `CollectionClassifier::make()->and($other)`.
 */
abstract class Classifier
{
    /**
     * Construct the classifier — the static, chainable entry to an instance.
     * Override when a classifier needs dependencies (e.g. `return app(static::class)`)
     * so DI resolution stays behind the same call site.
     */
    public static function make(): static
    {
        return new static();
    }
}
