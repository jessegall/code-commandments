<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Support\DataClassShape;
use JesseGall\CodeCommandments\Scribes\NeedsCodebase;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;

/**
 * Fixes {@see \JesseGall\CodeCommandments\Detectors\Backend\NewDataObjectDetector}: a rich
 * Spatie `Data` object built with `new` should go through `::from()` so the cast/map/nest
 * pipeline runs. Rewrites `new Foo(a: 1, b: 2)` → `Foo::from(['a' => 1, 'b' => 2])`,
 * resolving POSITIONAL arguments to property names via the constructor.
 *
 * It only rewrites where the result is PROVABLY equivalent — the filters are the whole
 * point of the scribe:
 *  - the target class must be VISIBLE in the codebase (else its shape can't be verified);
 *  - it must NOT remap input names (`#[MapInputName]`/`#[MapName]`) — `::from()` keys by the
 *    mapped name, so a property-name-keyed array would silently mismap;
 *  - every argument must resolve to a property name (a named arg, or a positional arg
 *    landing on a PROMOTED, non-variadic param). A spread or an unresolvable position is
 *    skipped. The detector still flags those — they just aren't auto-fixable.
 */
final class NewDataObjectScribe extends RepentScribe implements NeedsCodebase
{
    private ?DataClassShape $shape = null;

    public function withCodebase(Codebase $codebase): void
    {
        $this->shape = DataClassShape::forCodebase($codebase);
    }

    public function rewrite(array $findings): array
    {
        return $this->draft($findings)
            ->replace(fn (NodeMatch $match): ?string => $this->toFrom($match))
            ->rewrites();
    }

    private function toFrom(NodeMatch $match): ?string
    {
        $new = $match->node;

        if ($this->shape === null || ! $new instanceof New_ || ! $new->class instanceof Name) {
            return null;
        }

        $fqcn = $new->class->toString();
        $class = $this->shape->classFor($fqcn);

        // Unverifiable shape, or an input-name remap — can't guarantee the keys.
        if ($class === null || $this->shape->remapsInputNames($fqcn)) {
            return null;
        }

        $params = $class->getMethod('__construct')?->params ?? [];
        $source = $match->file->source;
        $entries = [];

        foreach ($new->args as $index => $arg) {
            $key = $arg->name?->toString() ?? $this->positionalKey($params, $index);

            if ($arg->unpack || $key === null) {
                return null;
            }

            $value = $this->slice($source, $arg->value->getStartFilePos(), $arg->value->getEndFilePos());
            $entries[] = "'{$key}' => {$value}";
        }

        $class = $this->slice($source, $new->class->getStartFilePos(), $new->class->getEndFilePos());

        return "{$class}::from([" . implode(', ', $entries) . '])';
    }

    /**
     * The property name a positional argument at $index maps to — the param at that
     * position, but only when it's a PROMOTED, non-variadic property (so its name IS the
     * property). Anything else is unresolvable, so the whole call is left alone.
     *
     * @param  list<\PhpParser\Node\Param>  $params
     */
    private function positionalKey(array $params, int $index): ?string
    {
        $param = $params[$index] ?? null;

        if ($param === null || $param->flags === 0 || $param->variadic || ! $param->var instanceof Variable || ! is_string($param->var->name)) {
            return null;
        }

        return $param->var->name;
    }

    private function slice(string $source, int $start, int $endInclusive): string
    {
        return substr($source, $start, $endInclusive + 1 - $start);
    }
}
