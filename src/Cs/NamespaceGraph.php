<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

use JesseGall\CodeCommandments\DependencyArrow;
use JesseGall\CodeCommandments\DependencyArrows;

/**
 * Which namespace references which in a C# codebase, read from what the compiler resolved — a type named in a
 * declaration, a value's type, a call's target, and the type arguments inside any of them. Only types the
 * codebase declares count; a reference within one namespace is no arrow.
 */
final class NamespaceGraph
{
    /**
     * @var array<string, string>  every type the codebase declares, by symbol => the namespace it is declared in
     */
    private array $homes = [];

    /**
     * @var list<DependencyArrow>
     */
    private array $arrows = [];

    public function __construct(Codebase $codebase)
    {
        foreach ($codebase->whereType()->get() as $type) {
            $this->homes[(string) $type->node->symbol] = self::namespaceOf($type);
        }

        foreach ($codebase->modules() as $module) {
            $this->walk($module->root, '', $module);
        }
    }

    /**
     * Every reference across namespaces, in the order the files are written.
     */
    public function arrows(): DependencyArrows
    {
        return new DependencyArrows($this->arrows);
    }

    /**
     * The references between independent namespaces — neither nested in the other. A namespace nested in
     * another is part of it, so for which way the dependencies point the two are one.
     */
    public function independentArrows(): DependencyArrows
    {
        return new DependencyArrows(array_values(array_filter($this->arrows, static fn (DependencyArrow $arrow): bool => ! self::nests($arrow->from, $arrow->to) && ! self::nests($arrow->to, $arrow->from))));
    }

    /**
     * Is $inner nested in $outer — `Shop.Orders.Lines` in `Shop.Orders`?
     */
    private static function nests(string $outer, string $inner): bool
    {
        return str_starts_with($inner, "{$outer}.");
    }

    /**
     * Record the arrows $node and everything under it write, $namespace being the namespace they are in.
     */
    private function walk(Node $node, string $namespace, ModuleFile $module): void
    {
        $here = $node->is('NamespaceDeclaration', 'FileScopedNamespaceDeclaration') ? (string) $node->symbol : $namespace;

        foreach ($here === '' ? [] : $this->reachedFrom($node) as $home) {
            if ($home !== $here) {
                $this->arrows[] = new DependencyArrow(new NodeMatch($node, $module), $here, $home);
            }
        }

        foreach ($node->children as $child) {
            $this->walk($child, $here, $module);
        }
    }

    /**
     * The namespaces of the codebase's own types $node names — through its resolved type and the types inside
     * it, or the type its call resolves to — each once.
     *
     * @return list<string>
     */
    private function reachedFrom(Node $node): array
    {
        $symbols = [...($node->type?->namedTypes() ?? []), ...array_filter([$node->target?->type])];

        return array_values(array_unique(array_filter(array_map(fn (string $symbol): ?string => $this->homes[$symbol] ?? null, $symbols))));
    }

    /**
     * The namespace $type is declared in — none for a type in the global namespace.
     */
    private static function namespaceOf(NodeMatch $type): string
    {
        $declaration = array_values(array_filter($type->module->ancestorsOf($type->node), static fn (Node $node): bool => $node->is('NamespaceDeclaration', 'FileScopedNamespaceDeclaration')))[0] ?? null;

        return (string) $declaration?->symbol;
    }
}
