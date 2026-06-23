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
 * Flag a closure whose whole body forwards its parameters, in order and
 * unchanged, to ONE call — `fn ($x) => Foo::bar($x)` is just `Foo::bar(...)`.
 * PHP 8.1's first-class callable syntax says it directly.
 *
 * Advisory only (not auto-fixed): a first-class callable exposes the target's
 * real signature, so if the higher-order caller passes EXTRA args (e.g.
 * Collection::map's `$key`) and the target accepts them (optional 2nd param /
 * variadic), the swap changes behaviour. That arity check needs the target's
 * signature, so the author confirms before applying.
 */
#[IntroducedIn('1.143.0')]
class PreferFirstClassCallableProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'A forwarding closure should be a first-class callable — fn ($x) => f($x) is f(...)';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A closure (`fn` or single-`return` closure) whose body is exactly ONE call that passes the closure\'s parameters straight through, in order, with nothing else — no extra args, no reordering, no wrapping the result.')
            ->leaveWhen('the closure does more than forward — adds args (`f($x, true)`), reshapes (`f($b, $a)`, `f($x->id)`), wraps the result (`g(f($x))`, `f($x) + 1`); OR the higher-order caller passes MORE args than the closure declares (e.g. `Collection::map` passes `$key` as a 2nd arg) and the target would accept them (an optional 2nd param / variadic), which changes behaviour.')
            ->whenUnsure('check the target\'s signature: if it takes only the forwarded params (extra positional args are dropped), use the first-class callable `f(...)`; if it has an optional/variadic extra parameter the caller would now fill, keep the closure.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A closure that only forwards its argument to one callable is that callable with
extra ceremony — a parameter name, an arrow, and a repeat of the argument that
says nothing the callable's name doesn't. PHP 8.1's first-class callable syntax
(`foo(...)`, `Class::method(...)`, `$obj->method(...)`) is the value itself.

Bad — a forwarding closure:
    ->map(static fn (mixed $row): Spec => Spec::forArray($row))
    fn ($x) => trim($x)
    fn ($u) => $this->render($u)

Good — the callable itself:
    ->map(Spec::forArray(...))
    trim(...)
    $this->render(...)

WHAT FIRES — a closure whose body is EXACTLY one call (a function, static method,
or instance method) whose arguments are precisely the closure's parameters, in
declared order, each passed straight through (no extra args, no reordering, no
`$x->id`, no wrapping the result).

WHAT DOES NOT — added work (`f($x) + 1`, `g(f($x))`, `f($x, true)`), arg
reshaping (`f($b, $a)`, `f($x->id)`), a nullsafe call (`$x?->y()` has no
first-class form), or a zero-parameter closure. Not auto-fixed: a first-class
callable exposes the target's real arity, so when the higher-order caller passes
extra args (Collection::map's `$key`) that the target accepts, behaviour changes
— confirm the target's signature first.
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

        foreach ([Expr\ArrowFunction::class, Expr\Closure::class] as $closureType) {
            foreach ($finder->findInstanceOf($ast, $closureType) as $closure) {
                $callable = $this->forwardingCallable($closure, $content);

                if ($callable === null) {
                    continue;
                }

                $warnings[] = $this->warningAt(
                    $closure->getStartLine(),
                    sprintf('This closure only forwards its argument(s) to one call — use the first-class callable `%s`. (Leave it if the higher-order caller passes extra args the target would now receive — e.g. Collection::map\'s key.)', $callable),
                    $this->lineSnippet($content, $closure->getStartLine()),
                    'forwarding-closure',
                );
            }
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * The first-class callable string when $closure is a pure forwarder, else
     * null. e.g. `Foo::bar(...)`, `trim(...)`, `$this->render(...)`.
     *
     * @param  Expr\ArrowFunction|Expr\Closure  $closure
     */
    private function forwardingCallable(Expr $closure, string $content): ?string
    {
        $params = $closure->params;

        // Require >= 1 plain param (a zero-param closure ignores any args the
        // caller passes; the callable would receive them).
        if ($params === []) {
            return null;
        }

        $names = [];

        foreach ($params as $param) {
            if ($param->variadic || $param->byRef || ! $param->var instanceof Expr\Variable || ! is_string($param->var->name)) {
                return null;
            }

            $names[] = $param->var->name;
        }

        $call = $this->bodyCall($closure);

        if ($call === null || $call->isFirstClassCallable()) {
            return null;
        }

        if (! $this->argsForwardExactly($call, $names)) {
            return null;
        }

        return $this->callableText($call, $content);
    }

    /**
     * @param  Expr\ArrowFunction|Expr\Closure  $closure
     */
    private function bodyCall(Expr $closure): Expr\FuncCall|Expr\StaticCall|Expr\MethodCall|null
    {
        $expr = null;

        if ($closure instanceof Expr\ArrowFunction) {
            $expr = $closure->expr;
        } elseif (count($closure->stmts) === 1 && $closure->stmts[0] instanceof Node\Stmt\Return_) {
            $expr = $closure->stmts[0]->expr;
        }

        return $expr instanceof Expr\FuncCall || $expr instanceof Expr\StaticCall || $expr instanceof Expr\MethodCall
            ? $expr
            : null;
    }

    /**
     * Whether the call's arguments are EXACTLY $names, in order, each a plain
     * pass-through variable (no extra args, no unpack, no reshaping).
     *
     * @param  list<string>  $names
     */
    private function argsForwardExactly(Expr\FuncCall|Expr\StaticCall|Expr\MethodCall $call, array $names): bool
    {
        $args = $call->getArgs();

        if (count($args) !== count($names)) {
            return false;
        }

        foreach ($args as $i => $arg) {
            if ($arg->unpack
                || ! $arg->value instanceof Expr\Variable
                || $arg->value->name !== $names[$i]
            ) {
                return false;
            }
        }

        return true;
    }

    private function callableText(Expr\FuncCall|Expr\StaticCall|Expr\MethodCall $call, string $content): ?string
    {
        if ($call instanceof Expr\FuncCall) {
            return $call->name instanceof Node\Name ? $call->name->toString() . '(...)' : null;
        }

        if ($call instanceof Expr\StaticCall) {
            return $call->class instanceof Node\Name && $call->name instanceof Node\Identifier
                ? $call->class->toString() . '::' . $call->name->toString() . '(...)'
                : null;
        }

        // Instance method: $obj->method(...) — render the receiver verbatim.
        if ($call->name instanceof Node\Identifier) {
            return $this->slice($call->var, $content) . '->' . $call->name->toString() . '(...)';
        }

        return null;
    }

    private function slice(Node $node, string $content): string
    {
        $start = (int) $node->getStartFilePos();

        return substr($content, $start, (int) $node->getEndFilePos() - $start + 1);
    }

}
