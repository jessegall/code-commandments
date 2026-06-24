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
use PhpParser\PrettyPrinter;

/**
 * Flag a call that passes `$recv->has($x) ? $x : DEFAULT` to `$recv->method()`
 * — the receiver is asking ITSELF whether it has $x and handing the answer
 * back to itself. That redundant self-query belongs inside the callee as a
 * default/fallback parameter, so the call site reads `$recv->method($x)`.
 *
 * This is NOT a "prefer defaults" rule — it only relocates a presence-check
 * fallback that ALREADY exists at the call site against the SAME receiver.
 *
 *
 *
 * @method-generated-start
 * @method static presencePrefixes(array $value)
 * @method-generated-end
 */
#[IntroducedIn('1.69.0')]
class PreferDefaultFallbackProphet extends PhpCommandment
{
    /** Method-name prefixes that read as a presence/membership check. */
    private const PRESENCE_PREFIXES = ['has', 'contains', 'includes', 'exists'];

    public function description(): string
    {
        return 'Move a call-site presence-check-then-fallback into the callee as a default parameter';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A call passes `$recv->has($x) ? $x : DEFAULT` to a method on the '
                . 'SAME receiver — the object is second-guessing itself, and that '
                . 'same fallback gets re-stated at every call site.'
            )
            ->leaveWhen(
                'The fallback is caller-specific (different callers want different '
                . 'defaults), the callee cannot be changed (a vendor/builtin '
                . 'method), or a default would mask a missing-key bug that should '
                . 'surface. This rule relocates a redundant self-query — it is NOT '
                . 'about adding defaults.'
            )
            ->whenUnsure(
                'If the receiver owns the data its guard tests and the default is '
                . 'the same everywhere, push it into the callee. If the default '
                . 'varies per caller or a missing value should error, leave it.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A call site that writes `$recv->method($recv->has($x) ? $x : DEFAULT)` makes
the receiver interrogate itself: it asks "$recv, do you have $x?" and then
hands $recv the answer to use. The receiver already owns that data — it should
own the fallback too. Give the callee a default/fallback parameter and the
call site collapses to `$recv->method($x)`.

This rule is deliberately narrow. It is NOT "add a default everywhere":
fallbacks are often defensive programming that hides a missing value which
should have thrown. It fires ONLY on a presence-check-then-fallback that
ALREADY exists at the call site AND is a self-query — the guard is a presence
method on the SAME receiver being called. Everything else is left alone.

Bad — the receiver asks itself, then answers its own call:

    $context->runBranch(
        $context->hasBranch($handle) ? $handle : self::DEFAULT_HANDLE
    );

Good — the fallback lives in the callee:

    public function runBranch(string $handle, string $default = self::DEFAULT_HANDLE): void
    {
        $handle = $this->hasBranch($handle) ? $handle : $default;
        // …
    }

    // call site:
    $context->runBranch($handle);

WHAT FIRES — a `$recv->method(...)` (or `Class::method(...)`) call whose
argument is a full ternary `GUARD ? $x : FALLBACK` where GUARD is a presence
method (`has*`/`contains*`/`includes*`/`exists*`) on the SAME receiver, its
argument is the same `$x`, and FALLBACK is a constant (literal, `CONST`,
`self::DEFAULT`, enum case).

WHAT DOES NOT — a guard on a different object, a short ternary `?:`, a
computed (non-constant) fallback, or a guard that does not test the passed
value. The fix changes a callee signature, so it is NOT auto-fixed.

Configuration:

    Backend\PreferDefaultFallbackProphet::class => [
        'presence_prefixes' => ['has', 'contains', 'includes', 'exists'],
    ],
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $finder = new NodeFinder;
        $printer = new PrettyPrinter\Standard;
        $warnings = [];

        foreach ($finder->findInstanceOf($ast, Expr\MethodCall::class) as $call) {
            if ($call->isFirstClassCallable()) {
                continue;
            }

            $receiver = $call->var;
            $name = $call->name instanceof Node\Identifier ? $call->name->toString() : null;

            $warning = $this->inspectCall($call->getArgs(), $receiver, $name, $printer);

            if ($warning !== null) {
                $warnings[] = $warning;
            }
        }

        foreach ($finder->findInstanceOf($ast, Expr\StaticCall::class) as $call) {
            if ($call->isFirstClassCallable()) {
                continue;
            }

            $receiver = $call->class instanceof Node\Name ? $call->class : null;
            $name = $call->name instanceof Node\Identifier ? $call->name->toString() : null;

            if ($receiver === null) {
                continue;
            }

            $warning = $this->inspectCall($call->getArgs(), $receiver, $name, $printer);

            if ($warning !== null) {
                $warnings[] = $warning;
            }
        }

        if ($warnings === []) {
            return $this->righteous();
        }

        return Judgment::withWarnings($warnings);
    }

    /**
     * @param  array<Node\Arg>  $args
     */
    private function inspectCall(array $args, Node $receiver, ?string $calledMethod, PrettyPrinter\Standard $printer): ?\JesseGall\CodeCommandments\Results\Warning
    {
        if ($calledMethod === null) {
            return null;
        }

        foreach ($args as $arg) {
            if (! $arg->value instanceof Expr\Ternary || $arg->value->if === null) {
                continue;
            }

            $ternary = $arg->value;
            $value = $ternary->if;

            if (! $this->isSimpleValue($value) || ! $this->isConstantFallback($ternary->else)) {
                continue;
            }

            $guard = $this->guardMethodCall($ternary->cond);

            if ($guard === null) {
                continue;
            }

            // The guard must be a presence check on the SAME receiver, testing
            // the very value being passed.
            if (! $this->sameReceiver($receiver, $guard->var, $printer)
                || ! $this->isPresenceName($guard->name)
                || ! $this->argsInclude($guard->getArgs(), $value, $printer)
            ) {
                continue;
            }

            $guardName = $guard->name instanceof Node\Identifier ? $guard->name->toString() : 'has';
            $valueText = $printer->prettyPrintExpr($value);
            $fallbackText = $printer->prettyPrintExpr($ternary->else);

            return $this->warningAt(
                $ternary->getStartLine(),
                sprintf(
                    '`%s()` is handed `%s(%s) ? %s : %s` — a presence-check-then-fallback against the SAME receiver, which is asking itself a question to answer its own call. Give `%s()` a default parameter (e.g. `%s($value, $default = %s)`) and call `%s(%s)`; the lookup-or-default belongs in the callee, not restated at every call site.',
                    $calledMethod,
                    $guardName,
                    $valueText,
                    $valueText,
                    $fallbackText,
                    $calledMethod,
                    $calledMethod,
                    $fallbackText,
                    $calledMethod,
                    $valueText,
                ),
                null,
                'default-fallback:' . $calledMethod . ':' . $valueText,
            );
        }

        return null;
    }

    private function guardMethodCall(Expr $cond): ?Expr\MethodCall
    {
        if ($cond instanceof Expr\BooleanNot) {
            $cond = $cond->expr;
        }

        // A first-class callable guard (`$x->has(...)`) has no real args to
        // inspect and would assert on getArgs().
        if ($cond instanceof Expr\MethodCall && ! $cond->isFirstClassCallable()) {
            return $cond;
        }

        return null;
    }

    private function isPresenceName(Node\Identifier|Expr $name): bool
    {
        if (! $name instanceof Node\Identifier) {
            return false;
        }

        $method = strtolower($name->toString());

        foreach ($this->presencePrefixes() as $prefix) {
            if (str_starts_with($method, strtolower($prefix))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<Node\Arg>  $args
     */
    private function argsInclude(array $args, Expr $value, PrettyPrinter\Standard $printer): bool
    {
        $target = $printer->prettyPrintExpr($value);

        foreach ($args as $arg) {
            if ($printer->prettyPrintExpr($arg->value) === $target) {
                return true;
            }
        }

        return false;
    }

    private function sameReceiver(Node $callReceiver, Expr $guardReceiver, PrettyPrinter\Standard $printer): bool
    {
        $left = $callReceiver instanceof Expr ? $printer->prettyPrintExpr($callReceiver) : $this->nameText($callReceiver);
        $right = $printer->prettyPrintExpr($guardReceiver);

        if ($left === $right) {
            return true;
        }

        // Same object graph: both rooted at the same base variable / $this.
        return $this->rootOf($guardReceiver, $printer) === $left
            || ($callReceiver instanceof Expr && $this->rootOf($guardReceiver, $printer) === $this->rootOf($callReceiver, $printer));
    }

    private function rootOf(Expr $expr, PrettyPrinter\Standard $printer): string
    {
        while (true) {
            if ($expr instanceof Expr\MethodCall || $expr instanceof Expr\PropertyFetch || $expr instanceof Expr\NullsafePropertyFetch) {
                $expr = $expr->var;

                continue;
            }

            return $printer->prettyPrintExpr($expr);
        }
    }

    private function nameText(Node $node): string
    {
        return $node instanceof Node\Name ? $node->toString() : '';
    }

    private function isSimpleValue(Expr $value): bool
    {
        return $value instanceof Expr\Variable
            || $value instanceof Expr\PropertyFetch
            || $value instanceof Expr\NullsafePropertyFetch
            || $value instanceof Expr\StaticPropertyFetch;
    }

    private function isConstantFallback(?Expr $else): bool
    {
        return $else instanceof Node\Scalar
            || $else instanceof Expr\ConstFetch
            || $else instanceof Expr\ClassConstFetch;
    }

    /**
     * @return list<string>
     */
    private function presencePrefixes(): array
    {
        $configured = $this->config('presence_prefixes', self::PRESENCE_PREFIXES);

        return is_array($configured) && $configured !== []
            ? array_values(array_filter($configured, 'is_string'))
            : self::PRESENCE_PREFIXES;
    }
}
