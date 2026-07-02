<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * A type the grammar does not model in detail — a conditional (`A extends B ? X : Y`), a mapped
 * type, a template-literal type, a `keyof`/`readonly` operator. Rather than mis-parse or truncate
 * it, the parser captures the whole (bracket-balanced, arrow-aware) source region VERBATIM, so it
 * re-renders EXACTLY. This is the "can't fail" floor: an unmodelled type is preserved, never lost.
 */
final class VerbatimType extends TypeNode
{
    public function __construct(public readonly string $raw) {}

    public function render(): string
    {
        return $this->raw;
    }
}
