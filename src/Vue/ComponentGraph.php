<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

/**
 * The render tree — which component renders which, with what props. Indexed by child component
 * for reverse lookup: who renders this component, passing what. Every tag resolved to a real file
 * via {@see ModuleResolver}; global/unresolved tags become no edge. Page roots have no incoming.
 */
final class ComponentGraph
{
    /**
     * @param  array<string, list<ComponentUsage>>  $incoming  child file => usages of it
     */
    private function __construct(private readonly array $incoming) {}

    /**
     * The graph of NO components — nothing renders anything, so no usage is ever found. What
     * {@see PropTypes::none} is built over.
     */
    public static function empty(): self
    {
        return new self([]);
    }

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
