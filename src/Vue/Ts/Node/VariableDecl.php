<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

use JesseGall\CodeCommandments\Vue\Expr\Expr;

/**
 * A `const`/`let`/`var` declaration — its binding {@see Pattern}, an optional type annotation, and
 * an optional initializer. The initializer is kept as raw source ({@see initRaw}), and structurally
 * when it's recognisable: a {@see CallExpr} (`const { taxes } = useTaxTypes()`, `const open =
 * ref(false)`) or an arrow function's signature ({@see initParams} + {@see initReturnType} for
 * `const load = (): User[] => …`) — so the declaration's type can be read without re-scanning.
 */
final class VariableDecl extends Node
{
    /**
     * @param  ?list<Param>  $initParams  the params when the initializer is an arrow function
     */
    public function __construct(
        public readonly string $keyword,
        public readonly Pattern $pattern,
        public readonly ?TypeNode $typeAnnotation = null,
        public readonly ?string $initRaw = null,
        public readonly ?CallExpr $initCall = null,
        public readonly ?array $initParams = null,
        public readonly ?TypeNode $initReturnType = null,
        public readonly ?Expr $initializer = null,
    ) {}

    /**
     * The initializer as a real expression tree — so the call in `const node = root.querySelector(…)`
     * is reachable to a rule the same way a call standing alone as a statement is.
     */
    public function expressions(): array
    {
        return $this->initializer !== null ? [$this->initializer] : [];
    }

    public function callTo(string $callee): ?CallExpr
    {
        return $this->initCall?->callee === $callee ? $this->initCall : null;
    }

    public function declaredNames(): array
    {
        return $this->pattern->names();
    }

    /**
     * The initializer's own nodes — a `const x = useThing()` call, or the parameters of a
     * `const load = (page: number) => …` arrow, which are real parameters a rule judges like any
     * other. The binding pattern is NOT among them: its names are this declaration's own
     * ({@see declaredNames}), so walking into it would report the same name twice.
     */
    public function children(): array
    {
        return array_values(array_filter([$this->initCall, ...($this->initParams ?? [])]));
    }

    public function render(): string
    {
        $type = $this->typeAnnotation !== null ? ': ' . $this->typeAnnotation->render() : '';
        $init = $this->initRaw !== null ? ' = ' . $this->initRaw : '';

        return "{$this->keyword} {$this->pattern->render()}{$type}{$init};";
    }
}
