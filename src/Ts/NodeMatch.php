<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ts;

use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Span;
use JesseGall\CodeCommandments\Ts\Expr\Expr;
use JesseGall\CodeCommandments\Ts\Node\Node;

/**
 * A matched TypeScript {@see Node} that knows WHERE it is — the module-space twin of
 * {@see ElementMatch}, and the frontend's answer to the backend's {@see \JesseGall\CodeCommandments\Ast\NodeMatch}.
 * Non-final by design: subclass it to hang domain predicates a `where` closure can type-hint.
 */
class NodeMatch implements Located
{
    public function __construct(
        public readonly Node $node,
        public readonly ModuleFile $module,
    ) {}

    /**
     * The name this node declares — a function's, a class's, a parameter's. Empty for a node that
     * declares none, which is every statement.
     */
    public function name(): string
    {
        return $this->node->declaredNames()[0] ?? '';
    }

    public function line(): int
    {
        return $this->module->lineAt($this->node->start);
    }

    /**
     * The expressions this node holds at its own level, each sub-expression included.
     *
     * @return list<Expr>
     */
    public function expressions(): array
    {
        $all = [];

        foreach ($this->node->expressions() as $expression) {
            $all = [...$all, ...$expression->flatten()];
        }

        return $all;
    }

    public function file(): string
    {
        return $this->module->file;
    }

    public function location(): string
    {
        return $this->file() . ':' . $this->line();
    }

    /**
     * Where this match sits, so a scribe rewrites it the way it rewrites any other engine's match.
     */
    public function span(): Span
    {
        return $this->module->spanAt($this->node->start, $this->node->end);
    }

    /**
     * A short context for the report — what the node IS, named where it has a name.
     */
    public function scope(): string
    {
        $kind = self::shortName($this->node);

        return $this->name() === '' ? $kind : $kind . ' ' . $this->name();
    }

    private static function shortName(Node $node): string
    {
        $parts = explode('\\', $node::class);

        return $parts[count($parts) - 1];
    }
}
