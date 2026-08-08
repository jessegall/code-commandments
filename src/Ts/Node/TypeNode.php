<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ts\Node;

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

    /**
     * This type with Vue's reactive WRAPPERS removed — `Ref<V>` is `V`, because a template reads a
     * top-level ref as its value. A type that wraps nothing is already its own unwrapping, which is
     * why this is answered by the type rather than by an `instanceof` ladder outside it.
     */
    public function unwrapRef(): self
    {
        return $this;
    }

    /**
     * Does this type ADMIT ABSENCE — is `null` or `undefined` one of the values it allows? The
     * frontend reading of a nullable type, and the question every absence rule starts from, asked
     * of the type itself rather than re-derived by each holder of one (a field, a parameter, a
     * variable annotation, a return).
     */
    public function admitsAbsence(): bool
    {
        return false;
    }

    /**
     * The FIELDS this type resolves to — `{ a: A }` has them inline; a named reference has none of
     * its own, so it is handed $declared to look its name up with. A type that is neither declares
     * no fields. The caller passes HOW to resolve a name and the type decides whether it needs to.
     *
     * @param  callable(string): array<string, string>  $declared
     * @return array<string, string>
     */
    public function fieldsWith(callable $declared): array
    {
        return [];
    }
}
