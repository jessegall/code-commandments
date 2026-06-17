<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\CallGraph;

use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Shared recognition + canonicalisation for "inline subset of an enum's
 * cases" array literals.
 *
 * Both the {@see CodebaseIndex} (which counts how often a group is inlined
 * across the scroll) and the PreferEnumCaseGroups prophet (which decides
 * what to flag in one file) call THIS so their notion of "the same group"
 * can never drift: an array qualifies iff every item is a plain enum-case
 * fetch of the SAME enum, and the canonical key is the sorted, de-duplicated
 * set of `EnumFqcn::CaseName` strings — order and repetition don't matter.
 */
final class EnumCaseGroup
{
    /**
     * The resolved enum-case fetches of a qualifying array literal, or null
     * when the array does not qualify.
     *
     * Qualifies when:
     *   - it has >= $minGroup items,
     *   - every item is a plain `Expr\ClassConstFetch` whose const name is a
     *     real case (not `class`) and whose class is a resolvable Name,
     *   - all items resolve to the SAME enum FQCN.
     *
     * @param  array<string, string>  $uses  short alias => FQCN
     * @return array{fqcn: string, cases: list<string>}|null
     */
    public static function resolve(Expr\Array_ $array, array $uses, ?string $namespace, int $minGroup): ?array
    {
        if (count($array->items) < $minGroup) {
            return null;
        }

        $fqcn = null;
        $cases = [];

        foreach ($array->items as $item) {
            if (! $item instanceof Node\ArrayItem) {
                return null;
            }

            // A keyed entry, a spread, or a by-ref item is not a plain group.
            if ($item->key !== null || $item->byRef || $item->unpack) {
                return null;
            }

            $value = $item->value;

            if (! $value instanceof Expr\ClassConstFetch) {
                return null;
            }

            if (! $value->class instanceof Node\Name) {
                return null;
            }

            if (! $value->name instanceof Node\Identifier) {
                return null;
            }

            $caseName = $value->name->toString();

            if (strtolower($caseName) === 'class') {
                return null;
            }

            $resolved = NameResolver::resolve($value->class->toString(), $uses, $namespace);

            if ($fqcn === null) {
                $fqcn = $resolved;
            } elseif ($fqcn !== $resolved) {
                // Mixed enums — not a single named group.
                return null;
            }

            $cases[] = $caseName;
        }

        if ($fqcn === null || $cases === []) {
            return null;
        }

        return ['fqcn' => $fqcn, 'cases' => $cases];
    }

    /**
     * Canonical key for a resolved group: sorted, de-duplicated set of
     * `EnumFqcn::CaseName` strings, so order and repetition don't matter.
     *
     * @param  array{fqcn: string, cases: list<string>}  $resolved
     */
    public static function canonicalKey(array $resolved): string
    {
        $tokens = [];

        foreach ($resolved['cases'] as $case) {
            $tokens[$resolved['fqcn'] . '::' . $case] = true;
        }

        $tokens = array_keys($tokens);
        sort($tokens);

        return implode('|', $tokens);
    }

    /**
     * Whether the array literal is the haystack (2nd argument) of an
     * `in_array(...)` / `array_search(...)` call — that membership test is
     * the CompareSelf `equalsAny` rule's territory, so this rule must not
     * double-flag it.
     */
    public static function isMembershipNeedle(Expr\Array_ $array, Expr\FuncCall $call): bool
    {
        if (! $call->name instanceof Node\Name) {
            return false;
        }

        $fn = strtolower($call->name->toString());

        if ($fn !== 'in_array' && $fn !== 'array_search') {
            return false;
        }

        // A first-class callable (`in_array(...)`) carries only a
        // VariadicPlaceholder, so getArgs() would assert — and it is never a
        // membership test (#18).
        if ($call->isFirstClassCallable()) {
            return false;
        }

        // in_array($needle, $haystack) and array_search($needle, $haystack)
        // both carry the array as the 2nd positional argument.
        $args = $call->getArgs();

        return isset($args[1]) && $args[1]->value === $array;
    }
}
