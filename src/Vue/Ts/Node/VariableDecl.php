<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

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
    ) {}

    public function render(): string
    {
        $type = $this->typeAnnotation !== null ? ': ' . $this->typeAnnotation->render() : '';
        $init = $this->initRaw !== null ? ' = ' . $this->initRaw : '';

        return "{$this->keyword} {$this->pattern->render()}{$type}{$init};";
    }
}
