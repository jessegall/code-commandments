<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\ClassField;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Ast\TypeName;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\FlatFieldCluster;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Support\CamelCase;
use JesseGall\CodeCommandments\Support\ClassName;
use JesseGall\CodeCommandments\Support\FunctionWord;

/**
 * Flags a `#[TypeScript]` `Data` that spreads a value object flat across sibling scalar fields sharing a
 * camelCase prefix — `wireType`/`wireLabel` that should be `wire: Wire{type, label}` — WHEN a small value
 * object named for that prefix, whose fields ARE the flat remainders, already exists. Extend by depth, not
 * width. Stacked signals keep it unambiguous: on the wire (`#[TypeScript]`), scalar members, not all-bool
 * (else flags), no `…Id`/`…Uuid` (else a reference), prefix not a {@see FunctionWord}, and the VO exists.
 */
final class FlatFieldClusterDetector implements Detector
{
    /**
     * The smallest cluster worth flagging — two prefixed siblings already spread one concept flat.
     */
    private const int MIN_CLUSTER = 2;

    /**
     * Builtin scalar leaves that nest cleanly into a value object.
     */
    private const array SCALARS = ['string', 'int', 'float', 'bool'];

    /**
     * A value object is a SMALL bundle; a class with more public fields than this is an entity, not a VO.
     */
    private const int MAX_VALUE_OBJECT_FIELDS = 6;

    public function sin(): Sin
    {
        return new FlatFieldCluster();
    }

    public function find(Codebase $codebase): array
    {
        $shapes = $this->valueObjectShapes($codebase);

        return $codebase
            ->whereClass()
            ->where(static fn (SpatieDataNode $node): bool => $node->isTypeScriptData())
            ->where(fn (AstNode $node) => $this->flattensAValueObject($node, $shapes))
            ->get();
    }

    /**
     * Does this class carry a cluster of ≥{@see MIN_CLUSTER} public scalar fields sharing a camelCase
     * leading token that (a) isn't all-boolean, (b) isn't a foreign-key reference, (c) isn't a function
     * word, and (d) flattens a value object the codebase already models — a SMALL VO named for the prefix
     * whose own fields ARE the cluster's remainders (`wire{Type,Label}` restating `Wire{type, label}`)?
     *
     * @param  array<string, list<string>>  $shapes  VO short-name (and `…Data`-stripped) => its field names
     */
    private function flattensAValueObject(AstNode $class, array $shapes): bool
    {
        foreach ($this->clustersByPrefix($class) as $token => $cluster) {
            if (count($cluster) < self::MIN_CLUSTER) {
                continue;
            }

            if ($this->allBoolean($cluster)) {
                continue; // independent flags, not a record
            }

            if ($this->isReference($cluster)) {
                continue; // a `<prefix>Id` + label is a denormalized FK reference, legitimately flat
            }

            if (FunctionWord::isNonEntity($token)) {
                continue; // a quantifier/verb/modal prefix is grammar, not a sub-entity
            }

            $shape = $shapes[$token] ?? $shapes[$token . 'data'] ?? null;

            if ($shape !== null && $this->remaindersMatch($cluster, $shape)) {
                return true; // the flat fields ARE the fields of the value object named for the prefix
            }
        }

        return false;
    }

    /**
     * Are the cluster's remainders (each name past the prefix) all FIELDS of the value object? `wireType`/
     * `wireLabel` → {type, label}, every one a field of a `Wire{type, socket, label}` — so the flat fields
     * restate that VO (partly or wholly). The VO is already capped small ({@see MAX_VALUE_OBJECT_FIELDS}),
     * so a subset match can't collide with a wide entity.
     *
     * @param  list<ClassField>  $cluster
     * @param  list<string>  $shape  the VO's field names, lower-cased
     */
    private function remaindersMatch(array $cluster, array $shape): bool
    {
        foreach ($cluster as $field) {
            if (! in_array(strtolower(CamelCase::afterLeadingToken($field->name)), $shape, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The public scalar fields of $class grouped by their camelCase leading token (only fields that HAVE a
     * remainder past the token, so a bare `broadcast` never anchors a cluster).
     *
     * @return array<string, list<ClassField>>
     */
    private function clustersByPrefix(AstNode $class): array
    {
        $byToken = [];

        foreach ($class->fields() as $field) {
            if (! $field->isPublic || ! $this->isScalarLeaf($field->type)) {
                continue;
            }

            $token = CamelCase::leadingToken($field->name);

            if ($token !== '' && CamelCase::afterLeadingToken($field->name) !== '') {
                $byToken[$token][] = $field;
            }
        }

        return $byToken;
    }

    /**
     * The SMALL value objects the codebase declares, as `lower-cased short name => list of lower-cased public
     * field names`. Keyed by the class basename AND, for a `WireData`, also `wire`, so a `wire…` prefix finds
     * `WireData`. Only classes with ≤{@see MAX_VALUE_OBJECT_FIELDS} public fields count — a value object is a
     * small bundle; a wider class is an entity a prefix cluster must not be measured against.
     *
     * @return array<string, list<string>>
     */
    private function valueObjectShapes(Codebase $codebase): array
    {
        $shapes = [];

        foreach ($codebase->whereClass()->get() as $class) {
            $declared = $class->enclosingClassName();

            if ($declared === null) {
                continue; // an anonymous class names no shape
            }

            $short = strtolower(ClassName::short($declared));

            if ($short === '') {
                continue;
            }

            $fields = array_map(strtolower(...), $class->publicFieldNames());

            if ($fields === [] || count($fields) > self::MAX_VALUE_OBJECT_FIELDS) {
                continue;
            }

            $shapes[$short] = $fields;

            if (str_ends_with($short, 'data')) {
                $shapes[substr($short, 0, -4)] = $fields;
            }
        }

        return $shapes;
    }

    private function isScalarLeaf(?\PhpParser\Node $type): bool
    {
        return in_array(strtolower((string) TypeName::simpleName($type)), self::SCALARS, true);
    }

    /**
     * Does the cluster carry an `…Id` / `…Uuid` member? Then it REFERENCES another entity by key (plus a
     * denormalized label), which is honestly flat — not an owned value object to nest.
     *
     * @param  list<ClassField>  $cluster
     */
    private function isReference(array $cluster): bool
    {
        foreach ($cluster as $field) {
            if (str_ends_with($field->name, 'Id') || str_ends_with($field->name, 'Uuid')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<ClassField>  $cluster
     */
    private function allBoolean(array $cluster): bool
    {
        foreach ($cluster as $field) {
            if (strtolower((string) TypeName::simpleName($field->type)) !== 'bool') {
                return false;
            }
        }

        return true;
    }
}
