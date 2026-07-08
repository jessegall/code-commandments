<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\ClassField;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Ast\Support\TypeResolver;
use JesseGall\CodeCommandments\Ast\TypeName;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\CoupledFields;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Unpublished;

/**
 * DRAFT ({@see Unpublished}) — a class's own VALUE fields that are really ONE object. A clump is made of
 * VALUES (scalars, enums, value objects whose own fields are values — `Codebase::isValueType` walks the
 * chain), never injected SERVICES, so forwarding two collaborators into a sub-object is NOT a clump. Three
 * shapes, all read off the reusable field-usage engine ({@see \JesseGall\CodeCommandments\Ast\AstNode}):
 *   1. coupled     — ≥2 value fields assembled into one value together, recurrently OR guarded-then-assembled.
 *   2. cross-object — a value field + a reach through a sibling value-object, combined ≥2×.
 *   3. redundant mirror — a never-reassigned field whose name encodes a sibling object's public property.
 */
final class CoupledFieldsDetector implements Detector, Unpublished
{
    public function sin(): Sin
    {
        return new CoupledFields();
    }

    public function find(Codebase $codebase): array
    {
        $findings = [];

        foreach ($codebase->whereClass()->get() as $match) {
            if ($this->isCoupled($match, $codebase)) {
                $findings[] = $match;
            }
        }

        return $findings;
    }

    private function isCoupled(NodeMatch $match, Codebase $codebase): bool
    {
        $fields = $match->fields();

        if (count($fields) < 2) {
            return false;
        }

        $values = []; // value-typed field names (scalar/enum/value-object)

        foreach ($fields as $field) {
            if ($codebase->isValueType($field->type)) {
                $values[$field->name] = true;
            }
        }

        return $this->coupledValues($match, $values, count($fields))
            || $this->crossObjectClump($match, $codebase)
            || $this->redundantMirror($match, $fields, $codebase);
    }

    /**
     * A direct own field and a reach through a sibling object that are the SAME value-object type — two
     * symmetric PEERS of one concept the boundary split (`$this->fromNode` + `$this->edge->toNode`, both
     * `Node`). Combined in ≥2 places. When the two types DIFFER it is an aggregate holding related-but-
     * distinct parts (a graph + a node's id), not a clump — so the types must match, resolved through the
     * chain by {@see TypeResolver}. That the type is a parsed class keeps scalars/enums out.
     */
    private function crossObjectClump(NodeMatch $match, Codebase $codebase): bool
    {
        $class = $match->enclosingClassName();

        if ($class === null) {
            return false;
        }

        $resolver = TypeResolver::forCodebase($codebase);
        $peers = 0;

        foreach ($match->selfFieldNestedReachTriples() as [$direct, $base, $property]) {
            $directType = $resolver->propertyTypeOf($class, $direct);
            $baseType = $resolver->propertyTypeOf($class, $base);
            $reachedType = $baseType === null ? null : $resolver->propertyTypeOf($baseType, $property);

            if ($directType !== null
                && $reachedType !== null
                && ltrim($directType, '\\') === ltrim($reachedType, '\\')
                && $codebase->declarationMatch($directType) !== null
                && ++$peers >= 2) {
                return true;
            }
        }

        return false;
    }

    /**
     * ≥2 value fields that move as a unit: the SAME group assembled into one value in ≥2 places (recurrence
     * is Fowler's tell — a one-off `new X($a, $b)` is a mapping), OR a group guarded for absence together and
     * then assembled together (the `from`/`to` → one edge shape). A proper subset only — all fields folded
     * into one value is a 1:1 mapping, not a clump.
     *
     * @param  array<string, true>  $values
     */
    private function coupledValues(NodeMatch $match, array $values, int $fieldCount): bool
    {
        if (count($values) < 2) {
            return false;
        }

        $groups = $match->selfPropertyGroupsAssembled();
        $tested = array_flip($match->selfPropertiesTestedForAbsence(SpatieDataNode::OPTIONAL));
        $occurrences = [];

        foreach ($groups as $group) {
            $group = array_values(array_filter($group, static fn (string $name): bool => isset($values[$name])));

            if (count($group) < 2 || count($group) >= $fieldCount) {
                continue;
            }

            if (count(array_filter($group, static fn (string $name): bool => isset($tested[$name]))) >= 2) {
                return true; // guarded together AND assembled together
            }

            sort($group);
            $key = implode(',', $group);

            if ((($occurrences[$key] = ($occurrences[$key] ?? 0) + 1)) >= 2) {
                return true; // the same value group assembled in ≥2 places
            }
        }

        return false;
    }

    /**
     * A never-reassigned field whose name ENCODES a sibling value-object's public property — `workflowId`
     * for `workflow` + `id` — so the datum is duplicated. Both sides must be values.
     *
     * @param  list<ClassField>  $fields
     */
    private function redundantMirror(NodeMatch $match, array $fields, Codebase $codebase): bool
    {
        foreach ($fields as $object) {
            $type = TypeName::class($object->type) ?? TypeName::nullableClass($object->type);
            $declaration = $type === null ? null : $codebase->declarationMatch($type);

            if ($declaration === null) {
                continue;
            }

            foreach ($declaration->fields() as $inner) {
                if ($inner->isPublic && $codebase->isValueType($inner->type) && $this->mirroredBy($fields, $object, $inner, $match)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  list<ClassField>  $fields
     */
    private function mirroredBy(array $fields, ClassField $object, ClassField $inner, NodeMatch $match): bool
    {
        foreach ($fields as $mirror) {
            if ($mirror->name === $object->name) {
                continue;
            }

            if (strcasecmp($mirror->name, $object->name . ucfirst($inner->name)) === 0
                && TypeName::render($mirror->type) === TypeName::render($inner->type)
                && TypeName::render($mirror->type) !== ''
                && ! $match->rewritesSelfPropertyOutsideConstructor($mirror->name)) {
                return true;
            }
        }

        return false;
    }
}
