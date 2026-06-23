<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Classifiers;

/**
 * Boolean composition for a classifier base — `allOf`/`anyOf` + the fluent
 * `and`/`or`. A TRAIT, not an inherited base method, on purpose: `self` binds to
 * the class that `use`s it, so a {@see TypeClassifier} only composes with
 * TypeClassifiers (and an InstanceClassifier only with InstanceClassifiers) — the
 * kinds can't be mixed. (Overriding a `self`-typed parameter in a subclass is a
 * PHP contravariance error; a trait gives each base its own `self`-bound copy.)
 *
 * The using base supplies {@see composeClassifiers()} (it knows its own
 * `matches()` signature, so it builds the compound).
 */
trait ComposesClassifiers
{
    /** Matches only when ALL of $classifiers match (intersection). */
    public static function allOf(self ...$classifiers): self
    {
        return static::composeClassifiers($classifiers, true);
    }

    /** Matches when ANY of $classifiers match (union). */
    public static function anyOf(self ...$classifiers): self
    {
        return static::composeClassifiers($classifiers, false);
    }

    /** This classifier AND all of $others. */
    public function and(self ...$others): self
    {
        return static::allOf($this, ...$others);
    }

    /** This classifier OR any of $others. */
    public function or(self ...$others): self
    {
        return static::anyOf($this, ...$others);
    }

    /**
     * Build the compound classifier — implemented by each base, which knows its
     * own `matches()` signature.
     *
     * @param  list<self>  $classifiers
     */
    abstract protected static function composeClassifiers(array $classifiers, bool $requireAll): self;
}
