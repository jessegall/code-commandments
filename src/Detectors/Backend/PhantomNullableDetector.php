<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\TypeName;
use JesseGall\CodeCommandments\Ast\ValueFlow;
use JesseGall\CodeCommandments\Detectors\ChainDetector;
use JesseGall\CodeCommandments\Sins\Backend\PhantomNullable;
use JesseGall\CodeCommandments\Sins\Sin;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;

/**
 * A **phantom nullable** — a promoted field typed `?T` (or `T|null`, `T|Optional|null`) whose value,
 * followed through the whole program by the {@see \JesseGall\CodeCommandments\Ast\ValueFlow}
 * provenance graph, is ALWAYS consumed as present (dereferenced, or landed in a non-nullable
 * parameter) and NOT ONCE guarded (`?->`, `?? `, `!== null`, a truthiness test) — anywhere in its
 * flow, however many hops downstream. The type says "this can be missing"; the usage proves it never
 * is. Make the field non-nullable and let it be required, so construction fails hard on a real miss
 * instead of every consumer re-checking a value that's always there. Points at type-honesty.
 *
 * Usage-driven and conservative: it fires only on positive presence-evidence with ZERO contradiction,
 * and a guard anywhere in the value's provenance — or a flow the graph can't resolve — leaves the
 * field alone. The whole reasoning lives in `ValueFlow`; this detector only names the candidate
 * (a nullable promoted field) and reads the verdict.
 */
final class PhantomNullableDetector implements ChainDetector
{
    public function sin(): Sin
    {
        return new PhantomNullable();
    }

    public function chainPath(NodeMatch $finding, Codebase $codebase): array
    {
        if (! $finding->node instanceof Param || ! $finding->node->var instanceof Variable || ! is_string($finding->node->var->name)) {
            return [];
        }

        return $codebase->valueFlow()->chainPath($finding->enclosingClassName() ?? '', $finding->node->var->name);
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

            foreach ($node->getMethod('__construct')?->params ?? [] as $param) {
                if ($this->isPhantom($param, $fqcn, $flow)) {
                    $findings[] = new NodeMatch($param, $class->file, $codebase);
                }
            }
        }

        return $findings;
    }

    private function isPhantom(Param $param, string $fqcn, ValueFlow $flow): bool
    {
        if ($param->flags === 0 || ! TypeName::isNullable($param->type) || ! $param->var instanceof Variable || ! is_string($param->var->name)) {
            return false;
        }

        $verdict = $flow->verdict($fqcn, $param->var->name);

        return $verdict->assume >= 1 && $verdict->guard === 0;
    }
}
