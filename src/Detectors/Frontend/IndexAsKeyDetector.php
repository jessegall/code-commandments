<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Frontend\IndexAsKey;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Frontend\Detector;
use JesseGall\CodeCommandments\Vue\Directive;
use JesseGall\CodeCommandments\Vue\ElementMatch;
use JesseGall\CodeCommandments\Vue\Expr\Expr;
use JesseGall\CodeCommandments\Vue\Expr\Parser;
use JesseGall\CodeCommandments\Vue\Script;

/**
 * Detects `v-for` keyed by numeric index (`:key="index"`), which shifts when items
 * insert/reorder, breaking Vue patching and state. Only flags bare index identifier on
 * provably-array iterables (2-form ambiguous over objects). Points at vue-control-flow.
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
