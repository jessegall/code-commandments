<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * A `const`/`let`/`var` declaration — its binding {@see Pattern}, an optional type annotation, and
 * an optional initializer. The initializer is kept both as raw source ({@see initRaw}) and, when it
 * is syntactically a call, as a structured {@see CallExpr} — so `const { taxes } = useTaxTypes()`
 * exposes both the destructuring pattern and the callee, and `const open = ref(false)` exposes the
 * reactive wrapper and its argument.
 */
final class VariableDecl extends Node
{
    public function __construct(
        public readonly string $keyword,
        public readonly Pattern $pattern,
        public readonly ?TypeNode $typeAnnotation = null,
        public readonly ?string $initRaw = null,
        public readonly ?CallExpr $initCall = null,
    ) {}

    public function render(): string
    {
        $type = $this->typeAnnotation !== null ? ': ' . $this->typeAnnotation->render() : '';
        $init = $this->initRaw !== null ? ' = ' . $this->initRaw : '';

        return "{$this->keyword} {$this->pattern->render()}{$type}{$init};";
    }
}
