<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Frontend\IndexAsKey;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\Detector;
use JesseGall\CodeCommandments\Vue\Directive;
use JesseGall\CodeCommandments\Vue\ElementMatch;
use JesseGall\CodeCommandments\Vue\Expr\Expr;
use JesseGall\CodeCommandments\Vue\Expr\Parser;
use JesseGall\CodeCommandments\Vue\Script;

/**
 * A `v-for` whose `:key` is the loop INDEX — `v-for="(item, index) in items" :key="index"`.
 * The index is positional, not identity: insert or reorder an item and every key shifts, so
 * Vue patches the wrong nodes and component state (inputs, focus, transitions) leaks across
 * rows. Key by something STABLE (`item.id`). Points at vue-control-flow.
 *
 * The numeric index is the LAST alias of the binding, read off the parsed `v-for` AST. The
 * shapes differ, and so does the certainty:
 *  - `(value, key, index)` — the 3rd alias is ALWAYS the numeric index, so keying by it is
 *    unambiguously the sin.
 *  - `(item, index)` — the 2nd alias is the numeric index for an ARRAY, but the property KEY
 *    for an OBJECT (`(value, key) in obj` keyed by `key` is correct). So this is flagged ONLY
 *    when the iterable is PROVABLY an array — resolved through the type engine, never guessed
 *    from the alias name.
 *
 * Only a `:key` that is EXACTLY the index identifier is flagged — `:key="item.id"` or a
 * composite key (`asChain()` is null) is left alone.
 */
final class IndexAsKeyDetector implements Detector
{
    public function sin(): Sin
    {
        return new IndexAsKey();
    }

    public function find(Codebase $components): array
    {
        return $components
            ->whereElement()
            ->withDirective(Directive::For)
            ->where(static fn (ElementMatch $element): bool => self::keyedByIndex($element))
            ->get();
    }

    private static function keyedByIndex(ElementMatch $element): bool
    {
        $for = $element->attribute(Directive::For);
        $key = $element->propBindings()['key'] ?? null;

        if ($for === null || $key === null) {
            return false;
        }

        $loop = Parser::parseFor($for);
        $aliases = $loop->get('aliases');

        if (count($aliases) < 2 || $key->asChain() !== [$aliases[count($aliases) - 1]]) {
            return false; // no index variable, or the key isn't the bare index identifier
        }

        // The 3-form index is unambiguous; the 2-form index only counts over a real array.
        return count($aliases) >= 3 || self::iteratesArray($loop->get('iterable'), $element);
    }

    /**
     * Whether the `v-for` iterable resolves to an ARRAY — a bare identifier (a prop or local)
     * whose declared type is `T[]` / `Array<T>` / a tuple. A member chain, a call, or an
     * unresolved type is NOT confirmed an array, so the (possibly object-keyed) 2-form is left
     * alone — soundness over reach.
     */
    private static function iteratesArray(Expr $iterable, ElementMatch $element): bool
    {
        $root = $iterable->asChain();

        if ($root === null || count($root) !== 1) {
            return false;
        }

        $script = new Script($element->sfc->scriptContent());
        $type = $script->propTypes()[$root[0]] ?? $script->declaredType($root[0]);

        return $type !== null && self::isArrayType($type);
    }

    private static function isArrayType(string $type): bool
    {
        $type = trim($type);

        return str_ends_with($type, ']')               // `T[]`, `readonly T[]`, a tuple `[A, B]`
            || str_starts_with($type, 'Array<')
            || str_starts_with($type, 'ReadonlyArray<');
    }
}
