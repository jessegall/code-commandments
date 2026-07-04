<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\TypeName;
use JesseGall\CodeCommandments\Detectors\ChainDetector;
use JesseGall\CodeCommandments\Sins\Backend\PhantomNullable;
use JesseGall\CodeCommandments\Sins\Sin;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Stmt\Class_;

/**
 * A **phantom nullable** — a field typed `?T` (or `T | null`, `T | Optional | null`) whose value,
 * followed through the whole program by the {@see \JesseGall\CodeCommandments\Ast\ValueFlow}
 * provenance graph, is ALWAYS consumed as present (dereferenced, or landed in a non-nullable
 * parameter) and NOT ONCE guarded (`?->`, `?? `, `!== null`, a truthiness test) — anywhere in its
 * flow, however many hops downstream. The type says "this can be missing"; the usage proves it never
 * is. Make the field non-nullable and let it be required, so construction fails hard on a real miss
 * instead of every consumer re-checking a value that's always there. Points at type-honesty.
 *
 * Not just DTOs: EVERY nullable property of EVERY class is a candidate — a constructor-promoted one
 * and a plain declared `public ?T $x` alike. Usage-driven and conservative: it fires only on positive
 * presence-evidence with ZERO contradiction, and a guard anywhere in the value's provenance — or a
 * flow the graph can't resolve — leaves the field alone. The whole reasoning lives in `ValueFlow`;
 * this detector only names the candidate and reads the verdict.
 */
final class PhantomNullableDetector implements ChainDetector
{
    public function sin(): Sin
    {
        return new PhantomNullable();
    }

    public function chainPath(NodeMatch $finding, Codebase $codebase): array
    {
        $field = self::fieldName($finding->node);

        return $field === null ? [] : $codebase->valueFlow()->chainPath($finding->enclosingClassName() ?? '', $field);
    }

    public function find(Codebase $codebase): array
    {
        $flow = $codebase->valueFlow();
        $findings = [];

        foreach ($codebase->whereClass()->get() as $class) {
            $node = $class->node;

            if (! $node instanceof Class_) {
                continue;
            }

            $fqcn = ltrim(($node->namespacedName ?? null)?->toString() ?? '', '\\');

            foreach ($this->nullableFields($node) as [$field, $name]) {
                $verdict = $flow->verdict($fqcn, $name);

                if ($verdict->assume >= 1 && $verdict->guard === 0) {
                    $findings[] = new NodeMatch($field, $class->file, $codebase);
                }
            }
        }

        return $findings;
    }

    /**
     * Every nullable field of $class — its constructor-PROMOTED params and its DECLARED properties —
     * as `[declaration node, name]`.
     *
     * @return list<array{0: Node, 1: string}>
     */
    private function nullableFields(Class_ $class): array
    {
        $fields = [];

        foreach (AstNode::constructorParamsOf($class) as $param) {
            if ($param->flags !== 0 && TypeName::isNullable($param->type) && ($name = self::fieldName($param)) !== null) {
                $fields[] = [$param, $name];
            }
        }

        foreach ($class->getProperties() as $property) {
            foreach ($property->props as $declared) {
                if (TypeName::isNullable($property->type)) {
                    $fields[] = [$declared, $declared->name->toString()];
                }
            }
        }

        return $fields;
    }

    /**
     * The field name behind a finding node — a promoted {@see Param} or a declared {@see PropertyItem}.
     */
    private static function fieldName(?Node $node): ?string
    {
        if ($node instanceof Param) {
            return $node->var instanceof Variable && is_string($node->var->name) ? $node->var->name : null;
        }

        return $node instanceof PropertyItem ? $node->name->toString() : null;
    }
}
