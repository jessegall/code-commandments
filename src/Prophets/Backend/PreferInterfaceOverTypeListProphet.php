<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Results\Warning;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Flag classifying a thing by membership in a HARDCODED LIST OF TYPE NAMES — `in_array($x, ['Bag', 'Collection', 'Data'])` or `in_array($name, self::SOMETHING_BASES)` — and push the knowledge to the types instead (a marker interface they implement, or an AST/reflection question).
 */
#[IntroducedIn('2.71.0')]
class PreferInterfaceOverTypeListProphet extends PhpCommandment
{
    /** Membership tests whose haystack argument we inspect (needle, HAYSTACK, …). */
    private const MEMBERSHIP_FUNCS = ['in_array', 'array_search'];

    public function description(): string
    {
        return 'Classify via a marker interface or the AST, not a hardcoded list of type names';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Structural;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('Code decides "is this one of THESE kinds?" by testing membership in a hardcoded list of TYPE NAMES — `in_array($class, [\'Bag\', \'Collection\', \'Data\'])`, `in_array($node->name, self::DATA_BASES)`. The set belongs ON the types. If they share an interface/base (even a VENDOR or SPL one like `Traversable`/`Countable`), resolve the name to its FQCN and ask reflection: `is_a($fqcn, \\Traversable::class, true)` — that catches every subtype, including ones the list never heard of, and needs no editing. If you own the types and they share nothing, give them a marker interface and test `instanceof`.')
            ->leaveWhen('the list is NOT type names but values, keys, method names, or extensions (`[\'php\', \'js\']`, `[\'get\', \'input\']`) — legitimate data; or the listed types share NO resolvable common interface/base AND are not reflectable (truly unrelated classes), so there is nothing to ask; or the membership is a genuine one-off guard, not a classification the domain repeats. (Being VENDOR types is NOT a reason to leave it — you check the interface they already implement, not one of yours.)')
            ->whenUnsure('check whether the listed types share an interface or base — they usually do (`Traversable` for collections, `Countable` for sizeables, a domain base for your own). If so, replace the list with `is_a($fqcn, Shared::class, true)` / `instanceof`. A type-name list both over-fires (a `FooData` that is not a Data) and under-fires (a subtype the list omits) — exactly what a real type check avoids.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A hardcoded list of TYPE NAMES used to classify — `const DATA_BASES = ['Bag',
'Collection', 'Data']; … in_array($shortName, self::DATA_BASES)` — encodes, in
ONE central place, knowledge that actually belongs to the types themselves. It
both OVER-fires (a `ReportData` service that is not a Data object still matches
the suffix) and UNDER-fires (a real value object that does not follow the naming
silently slips through), and every new type means editing the list.

Bad — the set of "these kinds" lives in a name list:
    private const DATA_BASES = ['Bag', 'Collection', 'Data'];

    private function isDataClass(string $shortName): bool
    {
        return in_array($shortName, self::DATA_BASES, true);
    }

Good — the types declare what they are; you ask THEM:
    interface RepresentsData {}

    final class WidgetBag implements RepresentsData { /* … */ }

    private function isDataClass(object $x): bool
    {
        return $x instanceof RepresentsData;       // a new Data type just implements it
    }

…or, when the question is structural, let the AST / reflection answer it:
    $isEnum = $node instanceof Node\Stmt\Enum_;    // not in_array($name, ['…Enum'])
    $isData = (new ReflectionClass($fqcn))->implementsInterface(SpatieData::class);

WHAT FIRES — a membership test (`in_array($x, …)` / `array_search($x, …)`) whose
haystack is an array — inline or a class constant — of two or more string
literals that are TYPE-NAME-SHAPED (PascalCase, or a `\Namespaced\Fqcn`).

WHAT DOES NOT — a list of non-type values (extensions, keys, method names,
lowercase predicates like `is_array`), an associative lookup/metadata map (a
class => value table is not membership classification), or a single-element list.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $warnings = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Expr\FuncCall::class) as $call) {
            if (! $call->name instanceof Node\Name
                || ! in_array($call->name->toString(), self::MEMBERSHIP_FUNCS, true)
            ) {
                continue;
            }

            $haystack = $call->args[1] ?? null;

            if (! $haystack instanceof Node\Arg) {
                continue;
            }

            $names = $this->typeNameList($haystack->value, $ast);

            if ($names === null) {
                continue;
            }

            $warnings[] = $this->warningAt(
                $call->getStartLine(),
                sprintf(
                    'Classifying by a hardcoded list of type names (%s) — push the set onto the types: a marker interface they implement (test `$x instanceof Marker` / `is_a()`), or let the AST/reflection answer "is this an X?". A name list both over- and under-fires, and every new type means editing the list.',
                    $this->renderList($names),
                ),
                $this->lineSnippet($content, $call->getStartLine()),
                'type-name-list:' . implode(',', array_slice($names, 0, 3)),
            );
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * If $node is (or references) a sequential array of >= 2 type-name-shaped
     * string literals, the list of those names; else null.
     *
     * @param  array<Node>  $ast
     * @return list<string>|null
     */
    private function typeNameList(Expr $node, array $ast): ?array
    {
        $array = $this->resolveArray($node, $ast);

        if ($array === null) {
            return null;
        }

        $names = [];

        foreach ($array->items as $item) {
            // An associative entry (`'Foo' => …`) is a lookup map, not a
            // membership list — that is not the smell.
            if ($item === null || $item->key !== null || ! $item->value instanceof Node\Scalar\String_) {
                return null;
            }

            if (! $this->isTypeNameShaped($item->value->value)) {
                return null;
            }

            $names[] = $item->value->value;
        }

        return count($names) >= 2 ? $names : null;
    }

    /**
     * The array literal $node is, or that a `self::CONST` / `ClassName::CONST`
     * reference resolves to within $ast; else null.
     *
     * @param  array<Node>  $ast
     */
    private function resolveArray(Expr $node, array $ast): ?Expr\Array_
    {
        if ($node instanceof Expr\Array_) {
            return $node;
        }

        if (! $node instanceof Expr\ClassConstFetch || ! $node->name instanceof Node\Identifier) {
            return null;
        }

        $constName = $node->name->toString();

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\ClassConst::class) as $const) {
            foreach ($const->consts as $declared) {
                if ($declared->name->toString() === $constName && $declared->value instanceof Expr\Array_) {
                    return $declared->value;
                }
            }
        }

        return null;
    }

    /** Whether $value looks like a class name: PascalCase, or a namespaced FQCN. */
    private function isTypeNameShaped(string $value): bool
    {
        return preg_match('/^\\\\?[A-Z][A-Za-z0-9_]*(\\\\[A-Z][A-Za-z0-9_]*)*$/', $value) === 1;
    }

    /**
     * @param  list<string>  $names
     */
    private function renderList(array $names): string
    {
        $shown = array_slice($names, 0, 3);
        $rendered = "'" . implode("', '", $shown) . "'";

        return count($names) > 3 ? "[{$rendered}, …]" : "[{$rendered}]";
    }
}
