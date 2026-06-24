<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Support\Resolvers\Ast\ReceiverTypeResolver;
use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\RepentanceResult;
use JesseGall\CodeCommandments\Results\Sin;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Support\PackageDetector;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Keep Spatie's magic `::from()` inside the Data class. Two bans:
 *  1. No custom `from`-prefixed factory (`fromModel()`, `fromX()`) on a Data
 *     class — the prefix is reserved for Spatie's type-dispatching `from()`,
 *     and a same-typed `from*` makes `::from()` recurse forever → segfault.
 *  2. No external `SomeData::from(...)` — the magic entry point may only be
 *     self/static/parent::from() inside the class. Outside, call an explicit
 *     named factory (`SomeData::forX(...)` / `::forArray([...])`).
 *
 *
 *
 * @method-generated-start
 * @method static dataSuffixes(array $value)
 * @method-generated-end
 */
#[IntroducedIn('1.137.0')]
class NoExternalDataFromProphet extends PhpCommandment implements SinRepenter
{
    public function supported(): bool
    {
        return PackageDetector::hasSpatieData();
    }

    public function description(): string
    {
        return 'Keep Spatie ::from() inside the Data class — no custom from* factories, no external ::from()';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Structural;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A Data class defines a `from`-prefixed factory (`fromModel()`, '
                . '`fromX()`), or `SomeData::from(...)` is called from outside that '
                . 'Data class. The reserved prefix can make `::from()` recurse into '
                . 'a same-typed factory and segfault (exit 139).'
            )
            ->leaveWhen(
                '`self::from()` / `static::from()` / `parent::from()` is called '
                . 'inside the Data class itself (the one legitimate magic call '
                . 'site), or `from()` is called on a non-Data class (Carbon, an '
                . 'enum, a query builder) — those are not flagged.'
            )
            ->whenUnsure(
                'Rename a `from*` factory to a non-`from` prefix (`for*`/`make`/'
                . '`build`), and replace an external `X::from([...])` with the '
                . 'explicit `X::forArray([...])` entry point.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Spatie's `::from()` is a type-dispatching entry point: `Foo::from($x)` routes to
a `from{Type}($x)` factory when one exists. So the `from` prefix is RESERVED for
Spatie. Two rules keep it safe:

1. NEVER define a `from`-prefixed factory on a Data class — not even in-class.
   If a custom `from*` factory's parameter type matches the argument, `::from()`
   dispatches into it and recurses forever → PHP SEGFAULT (exit 139): no
   catchable exception, no stack trace. Banning `from*` factories makes the
   recursion structurally impossible.

2. NEVER call `::from()` from outside the Data class. The magic entry point may
   only be `self::from()` / `static::from()` / `parent::from()` INSIDE the class.
   From a command/controller/service/another Data class, call an explicit named
   factory instead.

❌ BAD — reserved prefix (can recurse → segfault):
    final class NodeSummaryData extends Data
    {
        public static function fromDescriptor(NodeDescriptor $d): self
        {
            return self::from([/* ... */]);
        }
    }

❌ BAD — external magic call:
    $payload = WorkflowSummaryData::from(['id' => $w->id]);   // external ::from()
    $data    = NodeDescriptorData::from($descriptor);         // external object dispatch

✅ CORRECT — non-`from` prefix; from() only self-called in-class:
    final class NodeSummaryData extends Data
    {
        use FromArrayOnly;

        public static function forDescriptor(NodeDescriptor $d): self
        {
            return self::from(['key' => $d->key, 'label' => $d->label]);  // self::from() in-class — fine
        }
    }

    // external call sites — only named, non-magic factories:
    $data = NodeSummaryData::forDescriptor($descriptor);
    $blank = EmptyEnvelope::make();
    $wrapped = NodeListData::forArray($rows);   // raw-array entry

WHAT FIRES:
  - `public static function fromX(...)` on a Data class (reserved prefix) — SIN.
  - `OtherData::from(...)` / `OtherData::fromX(...)` where the call site is not
    that class (self/static/parent excepted) — SIN.

WHAT DOES NOT:
  - `self::from()` / `static::from()` / `parent::from()` inside the Data class.
  - `from()` on a non-Data class (suffix-gated to `*Data`).

AUTO-FIX (only the safe, single-file case): an external bare-array call
`X::from([...])` is rewritten to `X::forArray([...])` — a non-`from` delegate
on the FromArrayOnly trait, so magic dispatch can never target it. The other
cases need a human: a `from*` factory rename touches call sites in other files
(a missed site fatals), and an external object `X::from($obj)` needs a
domain-specific `forX()` factory.

Configure via:

    Backend\NoExternalDataFromProphet::class => [
        'data_suffixes' => ['Data'],   // class-name suffixes that mark a Data class
        'severity' => 'sin',           // or 'warning' to advise without blocking
    ],
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $suffixes = $this->resolveSuffixes();
        $isSin = $this->config('severity', 'sin') !== 'warning';
        $finder = new NodeFinder;

        /** @var list<Sin|Warning> $findings */
        $findings = [];

        // 1. Reserved-prefix factory definitions on a Data class.
        foreach ($finder->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if (! $this->classIsData($class, $suffixes)) {
                continue;
            }

            foreach ($class->getMethods() as $method) {
                if (! $method->isStatic() || ! $this->isCustomFromName($method->name->toString())) {
                    continue;
                }

                $name = $method->name->toString();
                $message = sprintf(
                    'Data factory `%s()` uses the reserved `from` prefix — Spatie\'s magic `::from()` dispatches by type into `from{Type}()` and can recurse into a same-typed factory and segfault (exit 139). Rename it to a non-`from` prefix (`for*`/`make`/`build`).',
                    $name,
                );
                $findings[] = $this->emit($isSin, $method->getStartLine(), $message, $this->lineSnippet($content, $method->getStartLine()), 'data-from-def:' . $name, false);
            }
        }

        // 2. External ::from() / ::from*() calls to a Data target.
        $parents = [];
        $this->buildParentMap($ast, null, $parents);

        foreach ($finder->findInstanceOf($ast, Expr\StaticCall::class) as $call) {
            if (! $call->name instanceof Node\Identifier
                || ! $call->class instanceof Node\Name
                || $call->isFirstClassCallable()
            ) {
                continue;
            }

            $method = $call->name->toString();
            $magic = $method === 'from';

            if (! $magic && ! $this->isCustomFromName($method)) {
                continue;
            }

            $target = $call->class->getLast();

            if (in_array(strtolower($target), ['self', 'static', 'parent'], true)) {
                continue; // the one legitimate in-class magic call site
            }

            if (! $this->nameIsData($target, $suffixes)) {
                continue; // from() on a non-Data class (Carbon, enum, …)
            }

            if ($this->enclosingClassName($call, $parents) === $target) {
                continue; // calling its own class by name — same as self::
            }

            $autoFixable = $magic && $this->autoFixableArg($call, $ast);

            $message = $magic
                ? ($autoFixable
                    ? sprintf('`%s::from(...)` calls Spatie\'s magic `::from()` from outside the Data class. Use the explicit `%s::forArray(...)` entry point instead.', $target, $target)
                    : sprintf('`%s::from(...)` calls Spatie\'s magic object dispatch from outside the Data class. Add an explicit `%s::forX(Type $x): static` factory (`static::from($x->toArray())` inside the class) and call that.', $target, $target))
                : sprintf('`%s::%s(...)` calls a reserved-`from`-prefix factory from outside the Data class — that factory should be renamed to a `for*` prefix (see its definition).', $target, $method);

            $findings[] = $this->emit($isSin, $call->getStartLine(), $message, $this->lineSnippet($content, $call->getStartLine()), 'data-from-call:' . $target . ':' . $method, $autoFixable);
        }

        if ($findings === []) {
            return $this->righteous();
        }

        return $isSin
            ? new Judgment(sins: $findings)
            : Judgment::withWarnings($findings);
    }

    public function canRepent(string $filePath): bool
    {
        return pathinfo($filePath, PATHINFO_EXTENSION) === 'php';
    }

    /**
     * Auto-fix only the safe single-file case: an external bare-array call
     * `X::from([...])` becomes `X::forArray([...])` (a non-`from` delegate, so
     * magic dispatch can never target it). Everything else needs a human.
     */
    public function repent(string $filePath, string $content): RepentanceResult
    {
        if (! $this->canRepent($filePath)) {
            return RepentanceResult::unchanged();
        }

        $ast = $this->parse($content);

        if ($ast === null) {
            return RepentanceResult::unrepentant('Unable to parse PHP file');
        }

        $suffixes = $this->resolveSuffixes();
        $parents = [];
        $this->buildParentMap($ast, null, $parents);

        /** @var list<array{start: int, end: int, text: string}> $edits */
        $edits = [];
        $penance = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Expr\StaticCall::class) as $call) {
            if (! $call->name instanceof Node\Identifier
                || $call->name->toString() !== 'from'
                || ! $call->class instanceof Node\Name
                || $call->isFirstClassCallable()
                || ! $this->autoFixableArg($call, $ast)
            ) {
                continue;
            }

            $target = $call->class->getLast();

            if (in_array(strtolower($target), ['self', 'static', 'parent'], true)
                || ! $this->nameIsData($target, $suffixes)
                || $this->enclosingClassName($call, $parents) === $target
            ) {
                continue;
            }

            $edits[] = [
                'start' => (int) $call->name->getStartFilePos(),
                'end' => (int) $call->name->getEndFilePos(),
                'text' => 'forArray',
            ];
            $penance[] = sprintf('Rewrote external %s::from([...]) to %s::forArray([...])', $target, $target);
        }

        if ($edits === []) {
            return RepentanceResult::unchanged();
        }

        usort($edits, static fn (array $a, array $b): int => $b['start'] <=> $a['start']);

        foreach ($edits as $edit) {
            $content = substr($content, 0, $edit['start']) . $edit['text'] . substr($content, $edit['end'] + 1);
        }

        return RepentanceResult::absolved($content, $penance);
    }

    private function emit(bool $isSin, int $line, string $message, string $snippet, string $symbol, bool $autoFixable): Sin|Warning
    {
        return $isSin
            ? $this->sinAt($line, $message, $snippet, null, $symbol, $autoFixable)
            : $this->warningAt($line, $message, $snippet, $symbol, $autoFixable);
    }

    /**
     * A custom `from`-prefixed name: `from` followed by an uppercase letter or
     * digit (`fromModel`, `fromRows`). Excludes the bare magic `from` and the
     * FromArrayOnly trait's own `from()`.
     */
    private function isCustomFromName(string $name): bool
    {
        return (bool) preg_match('/^from[A-Z0-9]/', $name);
    }

    /**
     * Whether the single `from(...)` argument can be safely rewritten to
     * `forArray(...)` — i.e. it is an array: an array literal, or a variable
     * declared `array`/`?array` in the enclosing function. An object argument is
     * NOT auto-fixed here — that is ExplicitDataFactory's job (it synthesises a
     * forX() factory). An unresolved type is left for a human.
     *
     * @param  array<Node>  $ast
     */
    private function autoFixableArg(Expr\StaticCall $call, array $ast): bool
    {
        $args = $call->getArgs();

        if (count($args) !== 1 || $args[0]->unpack) {
            return false;
        }

        $value = $args[0]->value;

        if ($value instanceof Expr\Array_) {
            return true;
        }

        return $value instanceof Expr\Variable
            && is_string($value->name)
            && $this->isArrayType(ReceiverTypeResolver::paramTypeNode($value->name, $call, $ast));
    }

    private function isArrayType(?Node $type): bool
    {
        if ($type instanceof Node\NullableType) {
            return $this->isArrayType($type->type);
        }

        return $type instanceof Node\Identifier && strtolower($type->toString()) === 'array';
    }


    /**
     * @param  list<string>  $suffixes
     */
    private function classIsData(Node\Stmt\Class_ $class, array $suffixes): bool
    {
        return $class->extends instanceof Node\Name
            && $this->nameIsData($class->extends->getLast(), $suffixes);
    }

    /**
     * @param  list<string>  $suffixes
     */
    private function nameIsData(string $shortName, array $suffixes): bool
    {
        foreach ($suffixes as $suffix) {
            if (str_ends_with($shortName, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The short name of the class enclosing $node, or null at top level.
     *
     * @param  array<int, Node>  $parents
     */
    private function enclosingClassName(Node $node, array $parents): ?string
    {
        $cur = $parents[spl_object_id($node)] ?? null;

        while ($cur !== null) {
            if ($cur instanceof Node\Stmt\Class_) {
                return $cur->name?->toString();
            }

            $cur = $parents[spl_object_id($cur)] ?? null;
        }

        return null;
    }

    /**
     * @param  array<Node>  $nodes
     * @param  array<int, Node>  $map
     */
    private function buildParentMap(array $nodes, ?Node $parent, array &$map): void
    {
        foreach ($nodes as $node) {
            if (! $node instanceof Node) {
                continue;
            }

            if ($parent !== null) {
                $map[spl_object_id($node)] = $parent;
            }

            foreach ($node->getSubNodeNames() as $name) {
                $sub = $node->{$name};
                $this->buildParentMap(is_array($sub) ? $sub : [$sub], $node, $map);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function resolveSuffixes(): array
    {
        $suffixes = $this->config('data_suffixes', ['Data']);

        return is_array($suffixes) && $suffixes !== []
            ? array_values(array_filter($suffixes, 'is_string'))
            : ['Data'];
    }

}
