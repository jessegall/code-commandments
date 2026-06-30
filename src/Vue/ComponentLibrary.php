<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

/**
 * The components already in the codebase, fingerprinted so an extraction can REUSE a
 * fitting one instead of creating a duplicate. Each component is reduced to its root
 * {@see Element::shapeSignature} (the binding-agnostic skeleton) and, per declared
 * prop, the SET OF FIELDS the template displays off it. A {@see Boundary} fits a
 * component when their skeletons match AND each of the component's props can be bound
 * to an object the boundary renders the SAME fields off — so `order.customer.{name,
 * email}` reuses a `<UserCard :user>` that shows `user.{name, email}`, types ignored.
 */
final class ComponentLibrary
{
    /**
     * @param  list<array{path: string, name: string, shape: string, fields: array<string, list<string>>}>  $components
     */
    private function __construct(private readonly array $components) {}

    public static function from(Codebase $codebase): self
    {
        $components = [];

        foreach ($codebase->components() as $sfc) {
            $root = self::root($sfc);

            if ($root === null) {
                continue;
            }

            $props = (new Script($sfc->scriptContent()))->propTypes();
            $byPrefix = self::fieldsByPrefix(self::chains($root));

            // keep only the prefixes that are a declared prop (the component's surface)
            $fields = [];
            foreach ($byPrefix as $prefix => $set) {
                if (isset($props[$prefix]) && count($set) >= 2) {
                    $fields[$prefix] = $set;
                }
            }

            if ($fields === []) {
                continue;
            }

            $components[] = [
                'path' => $sfc->path,
                'name' => self::componentName($sfc->path),
                'shape' => $root->shapeSignature(),
                'fields' => $fields,
            ];
        }

        return new self($components);
    }

    /**
     * The existing component that fits this boundary — same skeleton, and every one of
     * its props bound to an object the boundary displays the same fields off — or null.
     */
    public function match(Boundary $boundary): ?ComponentReuse
    {
        $shape = $boundary->node->shapeSignature();
        $blockFields = self::fieldsByPrefix(self::chains($boundary->node));

        foreach ($this->components as $component) {
            if ($component['shape'] !== $shape || $component['path'] === $boundary->sfc->path) {
                continue;
            }

            $bindings = $this->bind($component['fields'], $blockFields);

            if ($bindings !== null) {
                return new ComponentReuse($component['path'], $component['name'], $bindings);
            }
        }

        return null;
    }

    /**
     * Assign each component prop to a distinct block object whose displayed field-set is
     * identical, returning prop => object-path (the binding expression), or null if any
     * prop has no match.
     *
     * @param  array<string, list<string>>  $componentFields
     * @param  array<string, list<string>>  $blockFields
     * @return array<string, string>|null
     */
    private function bind(array $componentFields, array $blockFields): ?array
    {
        $bindings = [];
        $used = [];

        foreach ($componentFields as $prop => $fields) {
            $match = null;

            foreach ($blockFields as $object => $objectFields) {
                if (! isset($used[$object]) && self::sameSet($fields, $objectFields)) {
                    $match = $object;
                    break;
                }
            }

            if ($match === null) {
                return null;
            }

            $bindings[$prop] = $match;
            $used[$match] = true;
        }

        return $bindings;
    }

    /**
     * The single top-level element of a component's template, or null when it isn't one
     * coherent element.
     */
    private static function root(Sfc $sfc): ?Element
    {
        $elements = $sfc->template->elements();

        return count($elements) === 1 ? $elements[0] : null;
    }

    /**
     * Group member chains by their prefix (all-but-last segment) → the set of leaf
     * fields read off it: `order.customer.name` + `order.customer.email` →
     * `['order.customer' => ['name', 'email']]`.
     *
     * @param  list<list<string>>  $chains
     * @return array<string, list<string>>
     */
    private static function fieldsByPrefix(array $chains): array
    {
        $byPrefix = [];

        foreach ($chains as $chain) {
            if (count($chain) < 2) {
                continue;
            }

            $prefix = implode('.', array_slice($chain, 0, -1));
            $leaf = $chain[count($chain) - 1];

            if (! in_array($leaf, $byPrefix[$prefix] ?? [], true)) {
                $byPrefix[$prefix][] = $leaf;
            }
        }

        return $byPrefix;
    }

    /**
     * Every member chain in an element subtree.
     *
     * @return list<list<string>>
     */
    private static function chains(Element $node): array
    {
        $chains = [];

        foreach ([$node, ...$node->descendants()] as $element) {
            foreach ($element->expressions() as $expression) {
                $chains = array_merge($chains, $expression->chains());
            }
        }

        return $chains;
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private static function sameSet(array $a, array $b): bool
    {
        sort($a);
        sort($b);

        return $a === $b;
    }

    private static function componentName(string $path): string
    {
        return basename($path, '.vue');
    }
}
