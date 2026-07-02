<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * An `import` declaration in whatever form the source uses — named (`{ a, b as c }`), default,
 * namespace (`* as NS`), a type-only import, or the TS import-equals alias (`import X =
 * App.Http.View.Page`) the Spatie-generated globals use. It maps each local binding to the member
 * it came from ({@see bindings}: local => imported name, `default`, `*`, or the aliased path), knows
 * its {@see source} module (null for import-equals), and re-renders verbatim.
 */
final class ImportDecl extends Node
{
    /**
     * @param  array<string, string>  $bindings  local name => imported member / `default` / `*` / alias path
     */
    public function __construct(
        public readonly array $bindings,
        public readonly ?string $source,
        public readonly bool $typeOnly,
        private readonly string $raw,
    ) {}

    public function render(): string
    {
        return $this->raw;
    }
}
