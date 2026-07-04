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
 * A field typed `?T` whose value is always consumed as present (never guarded) per the
 * {@see ValueFlow} provenance graph. Conservative: fires only on positive presence-evidence with
 * ZERO contradiction. Every nullable property of any class is a candidate.
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
