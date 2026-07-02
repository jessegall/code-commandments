<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * A TYPE in the tree — the thing a prop, a variable annotation, an interface member, or a function
 * signature resolves to. Beyond {@see render} (emit valid TS), a type reports every NAMED type it
 * {@see references} — recursively — so the extract scribe can find the parent-local
 * `interface`/`type` a prop's type depends on and carry it into the extracted child.
 */
abstract class TypeNode extends Node
{
    /**
     * The named types this type mentions, transitively (`Foo<Bar[]>` → `['Foo', 'Bar']`). Composite
     * types union their children's; a leaf keyword/literal references nothing.
     *
     * @return list<string>
     */
    public function references(): array
    {
        return [];
    }
}
