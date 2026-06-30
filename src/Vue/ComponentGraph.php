<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

/**
 * The render tree of a whole codebase — which component renders which, and with what props.
 * Built once over a {@see Codebase}: for every component, each child element that resolves
 * (via its import) to another component becomes an EDGE carrying the props bound at that site
 * ({@see ComponentUsage}).
 *
 * Indexed by the CHILD, so {@see usagesOf} answers "who renders this component, passing what"
 * — the reverse lookup top-down prop typing needs: a component's own untyped prop is whatever
 * its parents bind to it, resolved in their scope. The roots ({@see PageRoots}) have no
 * incoming edges; their props come from the server. Every tag is resolved to a real file
 * through the {@see ModuleResolver}; a global/unresolved tag is simply not an edge.
 */
final class ComponentGraph
{
    /**
     * @param  array<string, list<ComponentUsage>>  $incoming  child file => usages of it
     */
    private function __construct(private readonly array $incoming) {}

    public static function of(Codebase $codebase): self
    {
        $incoming = [];

        foreach ($codebase->components() as $parent) {
            $script = new Script($parent->scriptContent());
            $resolver = ModuleResolver::forFile($parent->path);

            foreach ($parent->elements()->get() as $element) {
                if (! $element->isComponent()) {
                    continue;
                }

                $specifier = $script->importSpecifier($element->tag);
                $bindings = $element->propBindings();

                if ($specifier === null || $bindings === []) {
                    continue; // a global/unresolved tag, or nothing passed — no typed edge
                }

                $child = $resolver->resolve($parent->path, $specifier);

                if ($child !== null) {
                    $incoming[$child][] = new ComponentUsage($parent, $bindings);
                }
            }
        }

        return new self($incoming);
    }

    /**
     * Every place $componentFile is rendered, with the props bound there.
     *
     * @return list<ComponentUsage>
     */
    public function usagesOf(string $componentFile): array
    {
        $key = realpath($componentFile);

        return $this->incoming[$key === false ? $componentFile : $key] ?? [];
    }
}
