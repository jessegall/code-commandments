<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Support\Resolvers\Ast\ReceiverTypeResolver;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Flag classifying a thing by membership in a HARDCODED LIST OF TYPE NAMES — `in_array($x, ['Bag', 'Collection', 'Data'])` or `in_array($name, self::SOMETHING_BASES)` — and push the knowledge to the types instead (a marker interface they implement, or an AST/reflection question).
 */
#[IntroducedIn('2.71.0')]
class PreferInterfaceOverTypeListProphet extends PhpCommandment
{
    /** Membership tests whose 2nd (haystack) argument we inspect. */
    private const MEMBERSHIP_FUNCS = ['in_array', 'array_search'];

    /** Set ops where ANY array argument may be the type-name list. */
    private const SET_FUNCS = ['array_intersect', 'array_diff', 'array_intersect_key', 'array_diff_key'];

    /** Suffix/prefix tests — a chain of these on type names is the classic smell. */
    private const AFFIX_FUNCS = ['str_ends_with', 'str_starts_with'];

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

When a set recurs (and especially when it mixes your types with unavoidable
vendor ones), give it a NAMED home — a Classifier (run `commandments scaffold`
for the base): the interface check and the vendor fallback live in one testable
class, and the call site reads `(new IterableClassifier)->matches($fqcn)`:
    final class IterableClassifier extends Classifier
    {
        protected function interface(): string { return \Traversable::class; }
    }

WHAT FIRES — any of these classifications by type NAME:
  1. a membership test — `in_array($x, …)` / `array_search($x, …)` — whose haystack
     is an array (inline or a class constant) of two or more TYPE-NAME-shaped
     elements: string literals (`'Bag'`, `'\Ns\Foo'`) OR `Foo::class` references;
  2. a set op — `array_intersect`/`array_diff`(`_key`) — against such a list;
  3. a boolean chain of two or more `str_ends_with`/`str_starts_with` on
     type-name-shaped affixes (`str_ends_with($n, 'Bag') || str_ends_with($n, 'Data')`);
  4. a boolean `||`/`&&` chain of `instanceof` against two or more DISTINCT types
     (`$x instanceof A || $x instanceof B`) — a runtime version of the same set.

The reusable home for ALL of these is a Classifier (`commandments scaffold`): it
takes an FQCN string OR an object, checks the shared interface(s) (+ a vendor
fallback), and COMPOSES — `Classifier::allOf($iterable, $data)`,
`$bag->or($collection)` — so the set is named, shared, and edited in one place.

WHAT DOES NOT — a list of non-type values (extensions, keys, method names,
lowercase predicates like `is_array`), an associative lookup/metadata map (a
class => value table is not membership classification), a single-element list, or
a single affix test (one `str_ends_with` is more likely a value check than a
type classification).
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $finder = new NodeFinder;
        $warnings = [];

        // 1 + 2: a membership / set-op test against a list of type names.
        foreach ($finder->findInstanceOf($ast, Expr\FuncCall::class) as $call) {
            if (! $call->name instanceof Node\Name) {
                continue;
            }

            $fn = $call->name->toString();
            $names = null;

            if (in_array($fn, self::MEMBERSHIP_FUNCS, true) && ($call->args[1] ?? null) instanceof Node\Arg) {
                $names = $this->typeNameList($call->args[1]->value, $ast);
            } elseif (in_array($fn, self::SET_FUNCS, true)) {
                foreach ($call->args as $arg) {
                    if ($arg instanceof Node\Arg && ($names = $this->typeNameList($arg->value, $ast)) !== null) {
                        break;
                    }
                }
            }

            if ($names !== null) {
                $warnings[] = $this->listWarning($call, $names, $content);
            }
        }

        // 3: a boolean `||` chain of `instanceof` against >= 2 DISTINCT types —
        // `$x instanceof A || $x instanceof B` — a runtime "is it one of these
        // kinds?" that a Classifier makes reusable, named, and edited in one place.
        foreach ($this->instanceofChains($ast, $finder) as $chain) {
            $first = $chain['nodes'][0];
            $warnings[] = $this->warningAt(
                $first->getStartLine(),
                sprintf(
                    'A chain of `instanceof` against %d types (%s) classifies by a hardcoded type set — move it to a Classifier so the set is shared, named, and edited in ONE place: `(new XClassifier)->matches($x)` (test the interface they share, e.g. `is_a()` / a marker). Inline `instanceof` chains get copy-pasted and drift.',
                    count($chain['types']),
                    $this->renderList($chain['types']),
                ),
                $this->lineSnippet($content, $first->getStartLine()),
                'instanceof-chain:' . implode(',', array_slice($chain['types'], 0, 3)),
            );
        }

        // 4: a boolean chain of >= 2 suffix/prefix tests on type names (the
        // `str_ends_with($n, 'Bag') || str_ends_with($n, 'Data')` smell), grouped
        // by the function they live in so one chain is one finding.
        foreach ($this->affixChains($ast, $finder) as $affixes) {
            $first = $affixes[0];
            $warnings[] = $this->warningAt(
                $first->getStartLine(),
                sprintf(
                    'Classifying a type by its NAME suffix/prefix (%s) — the same "is it one of these kinds?" decision as a name list. Push it onto the types: a marker interface (test `$x instanceof Marker` / `is_a()`), or ask the AST/reflection. A suffix both over-fires (a `ReportData` that is not a Data) and under-fires (a value object that breaks the convention).',
                    $this->renderAffixes($affixes),
                ),
                $this->lineSnippet($content, $first->getStartLine()),
                'type-name-affix:' . $first->getStartLine(),
            );
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * Outermost boolean (`||` / `&&`) expressions that test `instanceof` against
     * two or more DISTINCT types — `$x instanceof A || $x instanceof B`,
     * `$x instanceof Iterable && $x instanceof Data`. One chain = one finding.
     *
     * @param  array<Node>  $ast
     * @return list<array{nodes: list<Expr\Instanceof_>, types: list<string>}>
     */
    private function instanceofChains(array $ast, NodeFinder $finder): array
    {
        $booleans = array_merge(
            $finder->findInstanceOf($ast, Expr\BinaryOp\BooleanOr::class),
            $finder->findInstanceOf($ast, Expr\BinaryOp\BooleanAnd::class),
        );

        $chains = [];

        foreach ($booleans as $bool) {
            if ($this->nestedInAnother($bool, $booleans)) {
                continue; // only the outermost node of a chain — one finding
            }

            $nodes = [];
            $types = [];

            foreach ($finder->findInstanceOf([$bool], Expr\Instanceof_::class) as $check) {
                if ($check->class instanceof Node\Name) {
                    $nodes[] = $check;
                    $types[$check->class->toString()] = true;
                }
            }

            if (count($types) >= 2) {
                $chains[] = ['nodes' => $nodes, 'types' => array_keys($types)];
            }
        }

        return $chains;
    }

    /**
     * Whether $node's source range is STRICTLY inside another candidate's range.
     *
     * @param  list<Node>  $candidates
     */
    private function nestedInAnother(Node $node, array $candidates): bool
    {
        $start = (int) $node->getStartFilePos();
        $end = (int) $node->getEndFilePos();

        foreach ($candidates as $other) {
            if ($other === $node) {
                continue;
            }

            $oStart = (int) $other->getStartFilePos();
            $oEnd = (int) $other->getEndFilePos();

            if ($oStart <= $start && $oEnd >= $end && ($oStart < $start || $oEnd > $end)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $names
     */
    private function listWarning(Expr\FuncCall $call, array $names, string $content): Warning
    {
        return $this->warningAt(
            $call->getStartLine(),
            sprintf(
                'Classifying by a hardcoded list of type names (%s) — push the set onto the types: a marker interface they implement (test `$x instanceof Marker` / `is_a($fqcn, Marker::class)`), or let the AST/reflection answer "is this an X?". For vendor types you cannot annotate, check the interface they ALREADY share (e.g. `is_a($fqcn, \\Traversable::class, true)`). A name list both over- and under-fires.',
                $this->renderList($names),
            ),
            $this->lineSnippet($content, $call->getStartLine()),
            'type-name-list:' . implode(',', array_slice($names, 0, 3)),
        );
    }

    /**
     * If $node is (or references) a sequential array of >= 2 type-name elements
     * — string literals OR `Foo::class` references — the list of those names;
     * else null.
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
            if ($item === null || $item->key !== null) {
                return null;
            }

            $name = $this->typeName($item->value);

            if ($name === null) {
                return null;
            }

            $names[] = $name;
        }

        return count($names) >= 2 ? $names : null;
    }

    /**
     * The type name of an array element — a type-name-shaped string literal, or a
     * `Foo::class` / `\Ns\Foo::class` reference; else null.
     */
    private function typeName(Expr $value): ?string
    {
        if ($value instanceof Node\Scalar\String_) {
            return $this->isTypeNameShaped($value->value) ? $value->value : null;
        }

        if ($value instanceof Expr\ClassConstFetch
            && $value->name instanceof Node\Identifier && $value->name->toString() === 'class'
            && $value->class instanceof Node\Name
        ) {
            return $value->class->toString();
        }

        return null;
    }

    /**
     * The suffix/prefix-test chains: per enclosing function, the
     * `str_ends_with`/`str_starts_with` calls on TYPE-NAME-shaped affixes, when a
     * function has two or more (one chain = one finding).
     *
     * @param  array<Node>  $ast
     * @return list<list<Expr\FuncCall>>
     */
    private function affixChains(array $ast, NodeFinder $finder): array
    {
        $byFunction = [];

        foreach ($finder->findInstanceOf($ast, Expr\FuncCall::class) as $call) {
            if (! $call->name instanceof Node\Name
                || ! in_array($call->name->toString(), self::AFFIX_FUNCS, true)
            ) {
                continue;
            }

            $needle = $call->args[1] ?? null;

            if (! $needle instanceof Node\Arg
                || ! $needle->value instanceof Node\Scalar\String_
                || ! $this->isTypeNameShaped($needle->value->value)
            ) {
                continue;
            }

            $fn = ReceiverTypeResolver::enclosingFunction($call, $ast);
            $byFunction[$fn?->getStartFilePos() ?? -1][] = $call;
        }

        return array_values(array_filter($byFunction, static fn (array $calls): bool => count($calls) >= 2));
    }

    /**
     * @param  list<Expr\FuncCall>  $affixes
     */
    private function renderAffixes(array $affixes): string
    {
        $rendered = array_map(static function (Expr\FuncCall $c): string {
            /** @var Node\Scalar\String_ $lit */
            $lit = $c->args[1]->value;

            return $c->name->toString() . "(…, '" . $lit->value . "')";
        }, array_slice($affixes, 0, 2));

        return implode(' || ', $rendered) . (count($affixes) > 2 ? ' || …' : '');
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

    /**
     * Whether $value looks like a class name — PascalCase or a namespaced FQCN,
     * and at least 3 chars (so short affixes like `Id` / a bare cap do not fire).
     */
    private function isTypeNameShaped(string $value): bool
    {
        return strlen(ltrim($value, '\\')) >= 3
            && preg_match('/^\\\\?[A-Z][A-Za-z0-9_]*(\\\\[A-Z][A-Za-z0-9_]*)*$/', $value) === 1;
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
