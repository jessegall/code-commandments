<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Classifiers;

use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;

/**
 * Decides whether a resolved FQCN belongs to a KIND — its own type name, an
 * interface it implements, or an ancestor it extends matching the kind — using
 * the {@see CodebaseIndex} (the package analyses source it cannot reflect on).
 *
 * The named, reusable home for the "is this a Collection / Data class / Registry /
 * …?" checks that prophets used to hand-roll with private name lists. A concrete
 * classifier names exactly what it classifies and declares its {@see self::types()}
 * (and any marker {@see self::interfaces()}); the matching logic lives here once.
 * Composes with {@see ComposesClassifiers} (TypeClassifiers only).
 */
abstract class TypeClassifier extends Classifier
{
    use ComposesClassifiers;

    /**
     * Short names (or FQCNs) of the TYPES that define this kind — matched on the
     * subject itself and on every ancestor in its extends-chain.
     *
     * @return list<string>
     */
    abstract protected function types(): array;

    /**
     * Short names of INTERFACES that mark this kind — matched against the
     * subject's implemented interfaces. Empty when the kind is defined by class
     * type alone.
     *
     * @return list<string>
     */
    protected function interfaces(): array
    {
        return [];
    }

    /**
     * Whether $fqcn is of this kind. The self/ancestor type check needs no index
     * (a known type name is enough); the interface + ancestor walk uses the index
     * when available — without it, only the type-name fast-path applies.
     */
    public function matches(string $fqcn, ?CodebaseIndex $index = null): bool
    {
        $fqcn = ltrim($fqcn, '\\');
        $typeNames = array_map($this->shortOf(...), $this->types());

        if (in_array($this->shortOf($fqcn), $typeNames, true)) {
            return true;
        }

        if ($index === null || $index->classByFqcn($fqcn) === null) {
            return false; // vendor / unknown and not a known type name
        }

        $interfaceNames = array_map($this->shortOf(...), $this->interfaces());

        foreach ($index->interfacesOf($fqcn) as $interface) {
            if (in_array($this->shortOf($interface), $interfaceNames, true)) {
                return true;
            }
        }

        $cursor = $fqcn;
        $depth = 0;

        while ($cursor !== null && $depth++ < 16) {
            $summary = $index->classByFqcn(ltrim($cursor, '\\'));

            if ($summary === null) {
                break;
            }

            if ($summary->parent !== null && in_array($this->shortOf($summary->parent), $typeNames, true)) {
                return true;
            }

            $cursor = $summary->parent;
        }

        return false;
    }

    protected function shortOf(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }

    /**
     * @param  list<self>  $classifiers
     */
    protected static function composeClassifiers(array $classifiers, bool $requireAll): self
    {
        return new class($classifiers, $requireAll) extends TypeClassifier {
            use MatchesComposedClassifiers;

            protected function types(): array
            {
                return [];
            }
        };
    }
}
