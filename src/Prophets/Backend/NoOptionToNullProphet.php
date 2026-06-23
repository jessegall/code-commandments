<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\RepentanceResult;
use JesseGall\CodeCommandments\Results\Tier;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Flag `->getOr(null)` — unwrapping an Option straight back into a nullable
 * value. That throws away the whole point of Option (no nulls to check) and
 * forces `?->` / `=== null` checks downstream. Act on the value with
 * `->map()`/`->each()`, require it with `->getOrThrow()`, or pass a REAL
 * default to `->getOr($default)`.
 */
#[IntroducedIn('1.74.0')]
class NoOptionToNullProphet extends PhpCommandment implements SinRepenter
{
    /** @var list<string> Option accessor methods whose null default is the smell. */
    private const DEFAULT_METHODS = ['getOr'];

    public function description(): string
    {
        return 'Do not unwrap an Option back to null with getOr(null)';
    }

    /**
     * Never flag the configured Option primitive itself — it defines the very
     * accessor methods this prophet watches.
     *
     * @return list<class-string>
     */
    public function exemptClasses(): array
    {
        $class = ltrim((string) ($this->config('option_class') ?: 'App\\Support\\Option'), '\\');

        return $class === '' ? [] : [$class];
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'An Option is unwrapped with `getOr(null)`, turning it straight '
                . 'back into a nullable value the surrounding code then null-checks '
                . '(`?->`, `=== null`) — discarding the Option entirely.'
            )
            ->leaveWhen(
                'The value-or-null is CARRIED straight to a sink that accepts null '
                . '— a nullable call argument / DTO field, a `return` matching the '
                . 'method\'s own `?T` contract, or a resolver factory arrow whose '
                . 'null means "no match". Those are not null-checks; the prophet '
                . 'already skips those positions. The smell is unwrap-THEN-check.'
            )
            ->whenUnsure(
                'If you find yourself null-checking the result, use map()/each()/'
                . 'getOrThrow() instead. getOr() should carry a REAL default, never null.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
`Option` exists so a value's presence is in the type, not a null you must
remember to check. `->getOr(null)` immediately undoes that — it converts
`Option<T>` back to `T|null`, and the code right after it goes back to
`?->`/`=== null` checks. You have paid for the Option and thrown it away.

Bad — unwrap to null, then null-check (the Option was pointless):
    $input = $this->inputByName($port)->getOr(null);
    if ($input?->socketType() === SocketType::Bag) { ... }

Good — stay inside the Option:
    // act only when present:
    $this->inputByName($port)->each(fn (Input $input) => /* … */);

    // map to another Option / value:
    $type = $this->inputByName($port)->map(fn (Input $i) => $i->socketType());

    // require it (throws if absent — when absence is a bug):
    $input = $this->inputByName($port)->getOrThrow();

    // or a REAL default (never null):
    $input = $this->inputByName($port)->getOr(Input::empty());

WHAT FIRES — `getOr()` handed null in any form: the `null` literal, `$x ?? null`,
a ternary with a null branch, or a local variable whose every assignment is null
(`$d = null; …->getOr($d)`). Laundering the null does not make it a real default.

WHAT DOES NOT — `getOr($realDefault)` with a genuine fallback, `getOrThrow()`,
`map()`, `each()`, AND carry-into-nullable positions where the value-or-null is
handed straight to a sink that accepts null rather than null-checked:

    new OutputSocket(isVisibleRule: $rule->getOr(null));   // nullable arg — carry
    return $this->find($x)->getOr(null);                   // method's own ?T contract
    IsX::make()->then(fn () => $this->build($x)->getOr(null));  // factory: null = no match

The smell is unwrap-THEN-null-check (`$x = …->getOr(null); if ($x === null)`),
not unwrap-and-hand-off. Configure the method name(s) if your accessor differs:

    Backend\NoOptionToNullProphet::class => [
        'methods' => ['getOr'],
    ],
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $methods = $this->methods();
        $parents = [];
        $this->buildParentMap($ast, null, $parents);

        /** @var array<int, array<string, true>> $nullLocalsCache */
        $nullLocalsCache = [];
        $warnings = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Expr\MethodCall::class) as $call) {
            if (! $call->name instanceof Node\Identifier
                || ! in_array($call->name->toString(), $methods, true)
                || $call->isFirstClassCallable()
            ) {
                continue;
            }

            $args = $call->getArgs();

            if (count($args) !== 1) {
                continue;
            }

            $fn = $this->enclosingFunction($call, $parents);
            $nullLocals = $this->nullOnlyLocals($fn, $nullLocalsCache);

            if (! $this->resolvesToNull($args[0]->value, $nullLocals)) {
                continue;
            }

            // Carry-into-nullable is legitimate: handing the value-or-null
            // straight to a sink that accepts null — a (nullable) call argument,
            // a `return` (the method's own ?T contract), or a resolver factory
            // arrow body (`fn () => …->getOr(null)`, where null = "no match").
            // The smell is unwrap-THEN-null-check, not unwrap-and-hand-off (#23).
            if ($this->isCarryPosition($call, $parents)) {
                continue;
            }

            $method = $call->name->toString();

            // #68: the SAFE auto-fixable sub-pattern — `$x = $opt->getOr(null);
            // if ($x === null) …` where $x is used SOLELY in the null test. Then
            // repent drops the local and rewrites the test to ->isEmpty()/
            // ->hasValue(). Any other use of $x (passed on, returned) makes it a
            // cascade — advisory only.
            $autoFixable = $this->unwrapNullCheckPattern($call, $parents, $nullLocalsCache) !== null;

            $warnings[] = $this->warningAt(
                $call->getStartLine(),
                sprintf(
                    '`->%s(...)` is handed a null default — unwrapping the Option back into a nullable value and forcing null checks downstream, which is exactly what Option exists to avoid. Act on the value with `->map(...)`/`->each(...)`, require it with `->getOrThrow()`, or pass a REAL default to `->%s($default)`. (Wrapping the null in a variable or `?? null` does not make it a real default.)',
                    $method,
                    $method,
                ),
                $this->lineSnippet($content, $call->getStartLine()),
                'option-to-null:' . $method,
                $autoFixable,
            );
        }

        if ($warnings === []) {
            return $this->righteous();
        }

        return Judgment::withWarnings($warnings);
    }

    /**
     * Whether the call sits in a "carry" position — its value-or-null result is
     * handed straight to a sink that accepts null, rather than null-checked.
     * Those are legitimate: a call argument, a `return` expression, or an arrow
     * function body (a resolver factory whose null means "no match").
     *
     * @param  array<int, Node>  $parents
     */
    private function isCarryPosition(Expr\MethodCall $call, array $parents): bool
    {
        $parent = $parents[spl_object_id($call)] ?? null;

        if ($parent instanceof Node\Arg || $parent instanceof Node\Stmt\Return_) {
            return true;
        }

        return $parent instanceof Expr\ArrowFunction && $parent->expr === $call;
    }

    /**
     * The safe #68 auto-fix shape: a `$x = OPT->getOr(<null>);` assignment whose
     * variable is used SOLELY in `$x === null` / `$x !== null` tests, AND whose
     * Option receiver OPT is side-effect-free (so inlining it does not re-run a
     * call). Any OTHER use of $x (passed on, returned, a `?->` chain) makes it a
     * cascade — advisory, never auto-fixed. Returns the parts to rewrite, or null.
     *
     * @param  array<int, Node>  $parents
     * @param  array<int, array<string, true>>  $nullLocalsCache
     * @return array{stmt: Node\Stmt\Expression, receiver: Expr, name: string, comparisons: list<array{node: Expr\BinaryOp, negated: bool}>}|null
     */
    private function unwrapNullCheckPattern(Expr\MethodCall $call, array $parents, array &$nullLocalsCache): ?array
    {
        $assign = $parents[spl_object_id($call)] ?? null;

        if (! $assign instanceof Expr\Assign || $assign->expr !== $call
            || ! $assign->var instanceof Expr\Variable || ! is_string($assign->var->name)
        ) {
            return null;
        }

        $stmt = $parents[spl_object_id($assign)] ?? null;

        if (! $stmt instanceof Node\Stmt\Expression) {
            return null;
        }

        if (! $this->isSideEffectFree($call->var)) {
            return null; // a call receiver would be re-evaluated when inlined
        }

        $args = $call->getArgs();

        if (count($args) !== 1) {
            return null;
        }

        $fn = $this->enclosingFunction($call, $parents);

        if (! $this->resolvesToNull($args[0]->value, $this->nullOnlyLocals($fn, $nullLocalsCache))) {
            return null;
        }

        $name = $assign->var->name;
        $scope = $fn ?? $stmt;
        $comparisons = [];

        foreach ((new NodeFinder)->findInstanceOf([$scope], Expr\Variable::class) as $var) {
            if ($var->name !== $name || $var === $assign->var) {
                continue;
            }

            $cmp = $this->nullComparisonOf($var, $parents);

            if ($cmp === null) {
                return null; // used somewhere other than a null test — a cascade
            }

            $comparisons[spl_object_id($cmp['node'])] = $cmp;
        }

        if ($comparisons === []) {
            return null;
        }

        return ['stmt' => $stmt, 'receiver' => $call->var, 'name' => $name, 'comparisons' => array_values($comparisons)];
    }

    private function isSideEffectFree(Expr $expr): bool
    {
        if ($expr instanceof Expr\Variable) {
            return true;
        }

        if ($expr instanceof Expr\PropertyFetch || $expr instanceof Expr\NullsafePropertyFetch) {
            return $this->isSideEffectFree($expr->var);
        }

        return false;
    }

    /**
     * @param  array<int, Node>  $parents
     * @return array{node: Expr\BinaryOp, negated: bool}|null
     */
    private function nullComparisonOf(Expr\Variable $var, array $parents): ?array
    {
        $parent = $parents[spl_object_id($var)] ?? null;

        if (! $parent instanceof Expr\BinaryOp\Identical && ! $parent instanceof Expr\BinaryOp\NotIdentical) {
            return null;
        }

        $other = $parent->left === $var ? $parent->right : $parent->left;

        if (! $this->isNullLiteral($other)) {
            return null;
        }

        return ['node' => $parent, 'negated' => $parent instanceof Expr\BinaryOp\NotIdentical];
    }

    public function canRepent(string $filePath): bool
    {
        return pathinfo($filePath, PATHINFO_EXTENSION) === 'php';
    }

    public function repent(string $filePath, string $content): RepentanceResult
    {
        if (! $this->canRepent($filePath)) {
            return RepentanceResult::unchanged();
        }

        $ast = $this->parse($content);

        if ($ast === null) {
            return RepentanceResult::unrepentant('Unable to parse PHP file');
        }

        $methods = $this->methods();
        $parents = [];
        $this->buildParentMap($ast, null, $parents);
        $nullLocalsCache = [];
        $edits = [];
        $penance = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Expr\MethodCall::class) as $call) {
            if (! $call->name instanceof Node\Identifier || ! in_array($call->name->toString(), $methods, true) || $call->isFirstClassCallable()) {
                continue;
            }

            $pattern = $this->unwrapNullCheckPattern($call, $parents, $nullLocalsCache);

            if ($pattern === null) {
                continue;
            }

            $receiverSrc = substr($content, (int) $pattern['receiver']->getStartFilePos(), (int) $pattern['receiver']->getEndFilePos() - (int) $pattern['receiver']->getStartFilePos() + 1);

            // Drop the whole `$x = …->getOr(null);` line.
            $stmt = $pattern['stmt'];
            $newlineBefore = strrpos(substr($content, 0, (int) $stmt->getStartFilePos()), "\n");
            $lineStart = $newlineBefore === false ? 0 : $newlineBefore + 1;
            $newlineAfter = strpos($content, "\n", (int) $stmt->getEndFilePos());
            $removeEnd = $newlineAfter === false ? strlen($content) - 1 : $newlineAfter;
            $edits[] = ['start' => $lineStart, 'end' => $removeEnd, 'text' => ''];

            foreach ($pattern['comparisons'] as $cmp) {
                $edits[] = [
                    'start' => (int) $cmp['node']->getStartFilePos(),
                    'end' => (int) $cmp['node']->getEndFilePos(),
                    'text' => $receiverSrc . ($cmp['negated'] ? '->hasValue()' : '->isEmpty()'),
                ];
            }

            $penance[] = sprintf('Dropped $%s = …->getOr(null) and rewrote its null check to %s->isEmpty()/->hasValue()', $pattern['name'], $receiverSrc);
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

    /**
     * Whether the argument is null — directly, or laundered through `?? null`,
     * a ternary with a null branch, or a local variable assigned only null.
     *
     * @param  array<string, true>  $nullLocals
     */
    private function resolvesToNull(Expr $expr, array $nullLocals): bool
    {
        if ($this->isNullLiteral($expr)) {
            return true;
        }

        // `$x ?? null` — the default is null.
        if ($expr instanceof Expr\BinaryOp\Coalesce && $this->isNullLiteral($expr->right)) {
            return true;
        }

        // `cond ? … : null` / `cond ? null : …` — can be null.
        if ($expr instanceof Expr\Ternary) {
            if ($expr->if instanceof Expr && $this->isNullLiteral($expr->if)) {
                return true;
            }

            if ($expr->else instanceof Expr && $this->isNullLiteral($expr->else)) {
                return true;
            }
        }

        // A local variable whose every assignment in this function is null.
        return $expr instanceof Expr\Variable
            && is_string($expr->name)
            && isset($nullLocals[$expr->name]);
    }

    private function isNullLiteral(Expr $expr): bool
    {
        return $expr instanceof Expr\ConstFetch
            && $expr->name instanceof Node\Name
            && strtolower($expr->name->toString()) === 'null';
    }

    /**
     * Names of local variables in $fn whose EVERY assignment is the null literal
     * (and which are not parameters) — so `$d = null; …->getOr($d)` is caught.
     *
     * @param  array<int, array<string, true>>  $cache
     * @return array<string, true>
     */
    private function nullOnlyLocals(?Node $fn, array &$cache): array
    {
        if ($fn === null) {
            return [];
        }

        $id = spl_object_id($fn);

        if (isset($cache[$id])) {
            return $cache[$id];
        }

        $stmts = $fn instanceof Node\Stmt\ClassMethod || $fn instanceof Node\Stmt\Function_ || $fn instanceof Expr\Closure
            ? ($fn->stmts ?? [])
            : [];

        /** @var array<string, bool> $allNull */
        $allNull = [];

        foreach ((new NodeFinder)->findInstanceOf($stmts, Expr\Assign::class) as $assign) {
            if (! $assign->var instanceof Expr\Variable || ! is_string($assign->var->name)) {
                continue;
            }

            $name = $assign->var->name;
            $allNull[$name] = ($allNull[$name] ?? true) && $this->isNullLiteral($assign->expr);
        }

        // Parameters get their value from callers — never treat them as null.
        if ($fn instanceof Node\Stmt\ClassMethod || $fn instanceof Node\Stmt\Function_ || $fn instanceof Expr\Closure || $fn instanceof Expr\ArrowFunction) {
            foreach ($fn->getParams() as $param) {
                if ($param->var instanceof Expr\Variable && is_string($param->var->name)) {
                    unset($allNull[$param->var->name]);
                }
            }
        }

        $nullLocals = [];

        foreach ($allNull as $name => $isNull) {
            if ($isNull) {
                $nullLocals[$name] = true;
            }
        }

        return $cache[$id] = $nullLocals;
    }

    /**
     * The nearest enclosing function-like node of $node, or null at top level.
     *
     * @param  array<int, Node>  $parents
     */
    private function enclosingFunction(Node $node, array $parents): ?Node
    {
        $cur = $parents[spl_object_id($node)] ?? null;

        while ($cur !== null) {
            if ($cur instanceof Node\Stmt\ClassMethod
                || $cur instanceof Node\Stmt\Function_
                || $cur instanceof Expr\Closure
                || $cur instanceof Expr\ArrowFunction
            ) {
                return $cur;
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
    private function methods(): array
    {
        $configured = $this->config('methods', self::DEFAULT_METHODS);

        return is_array($configured) && $configured !== []
            ? array_values(array_filter($configured, 'is_string'))
            : self::DEFAULT_METHODS;
    }

}
