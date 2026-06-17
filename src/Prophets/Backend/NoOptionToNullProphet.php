<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
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
class NoOptionToNullProphet extends PhpCommandment
{
    /** @var list<string> Option accessor methods whose null default is the smell. */
    private const DEFAULT_METHODS = ['getOr'];

    public function description(): string
    {
        return 'Do not unwrap an Option back to null with getOr(null)';
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
                'The value is genuinely handed to an external API whose contract '
                . 'is `?T` at a boundary and there is no Option-aware path. Rare — '
                . 'prefer map()/each()/getOrThrow().'
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
`map()`, `each()`. Configure the method name(s) if your Option accessor differs:

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

            $method = $call->name->toString();
            $warnings[] = $this->warningAt(
                $call->getStartLine(),
                sprintf(
                    '`->%s(...)` is handed a null default — unwrapping the Option back into a nullable value and forcing null checks downstream, which is exactly what Option exists to avoid. Act on the value with `->map(...)`/`->each(...)`, require it with `->getOrThrow()`, or pass a REAL default to `->%s($default)`. (Wrapping the null in a variable or `?? null` does not make it a real default.)',
                    $method,
                    $method,
                ),
                $this->lineAt($content, $call->getStartLine()),
                'option-to-null:' . $method,
            );
        }

        if ($warnings === []) {
            return $this->righteous();
        }

        return Judgment::withWarnings($warnings);
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

    private function lineAt(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return isset($lines[$line - 1]) ? trim($lines[$line - 1]) : '';
    }
}
