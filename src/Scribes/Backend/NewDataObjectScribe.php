<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Support\DataClassShape;
use JesseGall\CodeCommandments\Scribes\NeedsCodebase;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Scribes\Span;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;

/**
 * Rewrites `new Data()` to `::from()` where provably equivalent (class visible, no
 * input-name remap, resolvable arguments).
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

        $params = AstNode::constructorParamsOf($class);
        $source = $match->file->source;
        $entries = [];

        foreach ($new->args as $index => $arg) {
            $key = $arg->name?->toString() ?? $this->positionalKey($params, $index);

            if ($arg->unpack || $key === null) {
                return null;
            }

            $value = Span::slice($source, $arg->value->getStartFilePos(), $arg->value->getEndFilePos());
            $entries[] = "'{$key}' => {$value}";
        }

        $class = Span::slice($source, $new->class->getStartFilePos(), $new->class->getEndFilePos());

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
}
