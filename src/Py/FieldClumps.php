<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\Node\ClassDef;
use JesseGall\CodeCommandments\Py\Node\Param;

/**
 * A class's own value fields that are really one object — the Python twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\CoupledFieldsDetector}. A clump is made of values
 * (scalars, enums, dataclasses), never collaborators, and shows in one of two shapes: fields assembled into
 * one value together — again and again, or guarded for absence together first — and a field that mirrors
 * what a sibling field already holds.
 */
final readonly class FieldClumps
{
    public function __construct(private Codebase $codebase) {}

    public function isCoupled(ClassDef $class): bool
    {
        $fields = $class->fieldNames();

        if (count($fields) < 2) {
            return false;
        }

        $values = array_filter($fields, fn (string $field): bool => $class->attributeAnnotation($field)->isSomeAnd($this->isValue(...)));

        return $this->coupledValues($class, array_flip($values), count($fields)) || $this->mirrorsASibling($class, $fields);
    }

    /**
     * ≥2 value fields assembled into one value by themselves — `Window(self.start, self.end)`,
     * `(self.start, self.end)` — a proper subset of the fields, the same group in ≥2 places or ≥2 of it
     * guarded for absence together. Two among a wide projection of every field is a mapping, not a clump.
     *
     * @param  array<string, int>  $values
     */
    private function coupledValues(ClassDef $class, array $values, int $fieldCount): bool
    {
        $tested = array_flip($class->attributesTestedForAbsence());
        $occurrences = [];

        foreach ($this->assembledGroups($class) as $group) {
            $group = array_values(array_filter($group, static fn (string $name): bool => isset($values[$name])));

            if (count($group) < 2 || count($group) >= $fieldCount) {
                continue;
            }

            $guarded = array_filter($group, static fn (string $name): bool => isset($tested[$name]));

            if (count($guarded) >= 2 && count($guarded) * 2 >= count($group)) {
                return true;
            }

            sort($group);
            $key = implode(',', $group);

            if (($occurrences[$key] = ($occurrences[$key] ?? 0) + 1) >= 2) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every group of ≥2 distinct own fields handed, as they are, to one tuple, list or class being built.
     * A plain call passing them along is forwarding, not assembling one thing.
     *
     * @return list<list<string>>
     */
    private function assembledGroups(ClassDef $class): array
    {
        $groups = [];

        foreach ($class->expressionsWithin() as $expression) {
            $parts = match (true) {
                $expression->is(ExprKind::Tuple), $expression->is(ExprKind::List) => $expression->get('elements'),
                $expression->isCall() && $this->codebase->declaresClass($expression->get('callee')->dottedName()) => array_map(
                    static fn (Expr $argument): Expr => $argument->is(ExprKind::Keyword) ? $argument->get('value') : $argument,
                    $expression->get('arguments'),
                ),
                default => [],
            };
            $fields = array_values(array_unique(array_filter(array_map(static fn (Expr $part): string => $part->selfAttribute(), $parts))));

            if (count($fields) >= 2) {
                $groups[] = $fields;
            }
        }

        return $groups;
    }

    /**
     * Does a field mirror a value a sibling field already holds — `workflow_id` beside a `workflow` whose
     * class has an `id` of the same type? The datum then lives in two places.
     *
     * @param  list<string>  $fields
     */
    private function mirrorsASibling(ClassDef $class, array $fields): bool
    {
        foreach ($fields as $object) {
            $sibling = $class->attributeAnnotation($object)->andThen(fn (Expr $type) => $this->codebase->classNamed($type->spelledType()));

            if ($sibling->isNone()) {
                continue;
            }

            foreach ($sibling->unwrap()->fieldNames() as $inner) {
                $mirror = "{$object}_{$inner}";
                $same = $class->attributeAnnotation($mirror)->isSomeAnd(fn (Expr $type): bool => $sibling->unwrap()->attributeAnnotation($inner)->isSomeAnd(
                    static fn (Expr $theirs): bool => $theirs->spelledType() !== '' && $theirs->spelledType() === $type->spelledType(),
                ));

                if (in_array($mirror, $fields, true) && $same) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Is $annotation a value — a builtin scalar, an enum, or a dataclass — rather than a collaborator?
     */
    private function isValue(Expr $annotation): bool
    {
        $type = $annotation->optionalOf()->unwrapOr($annotation)->spelledType();

        return in_array($type, Param::SCALARS, true) || $this->codebase->enums()->isEnum($type) || $this->codebase->dataclasses()->named($type)->isSome();
    }
}
