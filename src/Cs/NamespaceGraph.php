<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

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
     * @var list<NamespaceArrow>
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
     *
     * @return list<NamespaceArrow>
     */
    public function arrows(): array
    {
        return $this->arrows;
    }

    /**
     * Each namespace the codebase references another from, with the namespaces it references.
     *
     * @return array<string, list<string>>
     */
    public function references(): array
    {
        $references = [];

        foreach ($this->arrows as $arrow) {
            $references[$arrow->from][$arrow->to] = true;
        }

        return array_map(static fn (array $targets): array => array_keys($targets), $references);
    }

    /**
     * Record the arrows $node and everything under it write, $namespace being the namespace they are in.
     */
    private function walk(Node $node, string $namespace, ModuleFile $module): void
    {
        $here = $node->is('NamespaceDeclaration', 'FileScopedNamespaceDeclaration') ? (string) $node->symbol : $namespace;

        foreach ($here === '' ? [] : $this->reachedFrom($node) as $home) {
            if ($home !== $here) {
                $this->arrows[] = new NamespaceArrow(new NodeMatch($node, $module), $here, $home);
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
