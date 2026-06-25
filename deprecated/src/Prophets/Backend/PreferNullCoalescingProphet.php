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
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\NodeFinder;
use PhpParser\PrettyPrinter;

/**
 * Flag a ternary that hand-rolls the null-coalescing operator — a self-fallback
 * where the condition tests the SAME value one branch returns and the other
 * branch is the fallback:
 *
 *     $x !== null ? $x : $default      →   $x ?? $default
 *     $x === null ? $default : $x      →   $x ?? $default
 *     isset($x)   ? $x : $default      →   $x ?? $default
 *     is_null($x) ? $default : $x      →   $x ?? $default
 *
 * These are provably equivalent to `??`. A genuine two-outcome ternary (a real
 * branch returning DIFFERENT values, like `$cond ? Group::from($r) :
 * Condition::from($r)`) is NOT a self-fallback and is left alone. The tested
 * value must be side-effect-free, so the one-eval rewrite can never change
 * behaviour.
 */
#[IntroducedIn('1.87.0')]
class PreferNullCoalescingProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Use `??` (or Option::unwrapOr) instead of a self-fallback ternary';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A ternary whose condition is a null/presence test on the SAME value '
                . 'one branch returns, the other branch being a fallback '
                . '(`$x !== null ? $x : $d`, `isset($x) ? $x : $d`). It is the '
                . 'null-coalescing operator written the long way — or, on an Option '
                . '(`$opt->isSome() ? $opt->unwrap() : $d`), a hand-rolled '
                . '`unwrapOr()`.'
            )
            ->leaveWhen(
                'The two branches return DIFFERENT values (a real decision, not a '
                . 'fallback), the test is a loose `== null` / `!= null` (which also '
                . 'swallows `0`, `\'\'`, `[]` — not the same as `??`), or the tested '
                . 'value has side effects (a method/function call evaluated in the '
                . 'condition).'
            )
            ->whenUnsure(
                'Ask whether one branch simply re-returns the thing the condition '
                . 'tested. If so it is `$x ?? $fallback`. If the branches are two '
                . 'distinct outcomes, leave it.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
The null-coalescing operator `??` exists for exactly one shape: "this value if
it is set, otherwise that one". A ternary that re-states that shape by hand —
testing a value in the condition and returning the SAME value in one branch —
is `??` wearing a costume. It is longer, evaluates the value twice, and hides
the intent.

When the value is an Option, the same idea is `$option->unwrapOr($default)`.

Bad — the condition tests $x and a branch returns $x:

    $label = $row->label !== null ? $row->label : 'untitled';
    $port  = isset($config['port']) ? $config['port'] : 8080;
    $name  = is_null($user) ? 'guest' : $user;

Good:

    $label = $row->label ?? 'untitled';
    $port  = $config['port'] ?? 8080;
    $name  = $user ?? 'guest';

WHAT FIRES — a FULL ternary `COND ? A : B` where COND is a STRICT null/presence
test (`V !== null`, `V === null`, `isset(V)`, `! isset(V)`, `is_null(V)`,
`! is_null(V)`) on a side-effect-free value V, and the branch on the "present"
side is V itself. The fix is `V ?? <fallback>`.

The same shape on an Option is `unwrapOr()`:

    // Bad — check then unwrap by hand:
    $name = $opt->isSome() ? $opt->unwrap() : 'guest';
    $name = $opt->isNone()  ? 'guest' : $opt->unwrap();

    // Good:
    $name = $opt->unwrapOr('guest');

WHAT DOES NOT — a real two-outcome ternary (the branches are different values),
a short ternary `?:`, a loose `== null` / `!= null` test (not equivalent to
`??`), or a value with side effects (a call), where collapsing two evaluations
into one would change behaviour.

Configuration (Option method names, if yours differ):

    Backend\PreferNullCoalescingProphet::class => [
        'present_checks'   => ['isSome', 'isPresent'],
        'absent_checks'    => ['isNone', 'isAbsent'],
        'unwrap_accessors' => ['unwrap', 'expect'],
    ],
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $printer = new PrettyPrinter\Standard;
        $warnings = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Expr\Ternary::class) as $ternary) {
            $parts = $this->coalesceParts($ternary, $printer);

            if ($parts !== null) {
                [$value, $fallback] = $parts;
                $valueText = $printer->prettyPrintExpr($value);
                $fallbackText = $printer->prettyPrintExpr($fallback);

                $warnings[] = $this->warningAt(
                    $ternary->getStartLine(),
                    sprintf(
                        'This ternary tests `%s` and hands it straight back — it is `%s ?? %s` written the long way. Use the null-coalescing operator: `%s ?? %s`. (If `%s` is an Option, this is `%s->unwrapOr(%s)`.)',
                        $valueText,
                        $valueText,
                        $fallbackText,
                        $valueText,
                        $fallbackText,
                        $valueText,
                        $valueText,
                        $fallbackText,
                    ),
                    'null-coalesce:' . $valueText,
                );

                continue;
            }

            $unwrapOr = $this->unwrapOrParts($ternary, $printer);

            if ($unwrapOr !== null) {
                [$receiverText, $fallback] = $unwrapOr;
                $fallbackText = $printer->prettyPrintExpr($fallback);

                $warnings[] = $this->warningAt(
                    $ternary->getStartLine(),
                    sprintf(
                        'This ternary checks `%s` for presence and unwraps it by hand — that is exactly what `unwrapOr()` is for. Use `%s->unwrapOr(%s)`.',
                        $receiverText,
                        $receiverText,
                        $fallbackText,
                    ),
                    'option-getor:' . $receiverText,
                );
            }
        }

        if ($warnings === []) {
            return $this->righteous();
        }

        return Judgment::withWarnings($warnings);
    }

    /**
     * If the ternary is a self-fallback null check, return its [value, fallback]
     * expressions (value on the "present" side, fallback on the other). Null
     * otherwise.
     *
     * @return array{Expr, Expr}|null
     */
    private function coalesceParts(Expr\Ternary $ternary, PrettyPrinter\Standard $printer): ?array
    {
        // A short ternary `$a ?: $b` is not in scope.
        if ($ternary->if === null) {
            return null;
        }

        $cond = $ternary->cond;
        $if = $ternary->if;
        $else = $ternary->else;

        // V !== null ? V : D
        if ($cond instanceof BinaryOp\NotIdentical) {
            $value = $this->nonNullOperand($cond);

            if ($value !== null && $this->isPure($value) && $this->eq($value, $if, $printer)) {
                return [$value, $else];
            }
        }

        // V === null ? D : V
        if ($cond instanceof BinaryOp\Identical) {
            $value = $this->nonNullOperand($cond);

            if ($value !== null && $this->isPure($value) && $this->eq($value, $else, $printer)) {
                return [$value, $if];
            }
        }

        $negated = false;

        if ($cond instanceof Expr\BooleanNot) {
            $negated = true;
            $cond = $cond->expr;
        }

        // isset(V) ? V : D   /   ! isset(V) ? D : V
        if ($cond instanceof Expr\Isset_ && count($cond->vars) === 1) {
            $value = $cond->vars[0];

            if (! $negated && $this->eq($value, $if, $printer)) {
                return [$value, $else];
            }

            if ($negated && $this->eq($value, $else, $printer)) {
                return [$value, $if];
            }
        }

        // is_null(V) ? D : V   /   ! is_null(V) ? V : D
        if ($cond instanceof Expr\FuncCall && $this->isNamedCall($cond, 'is_null')) {
            $value = $cond->getArgs()[0]->value;

            if (! $this->isPure($value)) {
                return null;
            }

            if (! $negated && $this->eq($value, $else, $printer)) {
                return [$value, $if];
            }

            if ($negated && $this->eq($value, $if, $printer)) {
                return [$value, $else];
            }
        }

        return null;
    }

    /**
     * If the ternary hand-rolls `Option::unwrapOr()` — a presence check on a
     * receiver whose "present" branch unwraps that SAME receiver — return its
     * [receiverText, fallback]. Null otherwise.
     *
     * @return array{string, Expr}|null
     */
    private function unwrapOrParts(Expr\Ternary $ternary, PrettyPrinter\Standard $printer): ?array
    {
        if ($ternary->if === null) {
            return null;
        }

        $cond = $ternary->cond;
        $negated = false;

        if ($cond instanceof Expr\BooleanNot) {
            $negated = true;
            $cond = $cond->expr;
        }

        if (! $cond instanceof Expr\MethodCall
            || $cond->isFirstClassCallable()
            || ! $cond->name instanceof Node\Identifier) {
            return null;
        }

        // isSome() → present when true; isNone() → present when false.
        $guard = strtolower($cond->name->toString());
        $presentWhenTrue = match (true) {
            in_array($guard, $this->presentChecks(), true) => true,
            in_array($guard, $this->absentChecks(), true) => false,
            default => null,
        };

        if ($presentWhenTrue === null) {
            return null;
        }

        $receiver = $cond->var;

        if (! $this->isPure($receiver)) {
            return null;
        }

        if ($negated) {
            $presentWhenTrue = ! $presentWhenTrue;
        }

        $presentBranch = $presentWhenTrue ? $ternary->if : $ternary->else;
        $fallbackBranch = $presentWhenTrue ? $ternary->else : $ternary->if;

        // The present branch must unwrap the SAME receiver, e.g.
        // `$opt->unwrap()`.
        if (! $presentBranch instanceof Expr\MethodCall
            || $presentBranch->isFirstClassCallable()
            || ! $presentBranch->name instanceof Node\Identifier
            || ! in_array(strtolower($presentBranch->name->toString()), $this->unwrapAccessors(), true)
            || $presentBranch->getArgs() !== []
            || ! $this->eq($presentBranch->var, $receiver, $printer)) {
            return null;
        }

        return [$printer->prettyPrintExpr($receiver), $fallbackBranch];
    }

    /**
     * The non-null operand of a strict comparison against `null` — or null if
     * neither (or both) operands are the `null` literal.
     */
    private function nonNullOperand(BinaryOp $cond): ?Expr
    {
        $leftNull = $this->isNullLiteral($cond->left);
        $rightNull = $this->isNullLiteral($cond->right);

        if ($leftNull === $rightNull) {
            return null;
        }

        return $leftNull ? $cond->right : $cond->left;
    }

    private function isNullLiteral(Expr $expr): bool
    {
        return $expr instanceof Expr\ConstFetch && strtolower($expr->name->toString()) === 'null';
    }

    private function isNamedCall(Expr\FuncCall $call, string $name): bool
    {
        return ! $call->isFirstClassCallable()
            && count($call->getArgs()) === 1
            && $call->name instanceof Node\Name
            && strtolower($call->name->getLast()) === $name;
    }

    /**
     * A side-effect-free value, safe to fold from two evaluations into one: a
     * variable, property/array access, or constant — never a call or `new`.
     */
    private function isPure(Expr $value): bool
    {
        $impure = (new NodeFinder)->findFirst([$value], static fn (Node $n): bool =>
            $n instanceof Expr\FuncCall
            || $n instanceof Expr\MethodCall
            || $n instanceof Expr\NullsafeMethodCall
            || $n instanceof Expr\StaticCall
            || $n instanceof Expr\New_);

        return $impure === null;
    }

    private function eq(Expr $a, Expr $b, PrettyPrinter\Standard $printer): bool
    {
        return $printer->prettyPrintExpr($a) === $printer->prettyPrintExpr($b);
    }

    /**
     * Option methods that read as "a value is present" (true → present).
     *
     * @return list<string>
     */
    private function presentChecks(): array
    {
        return $this->lowerList('present_checks', ['issome', 'ispresent', 'haselement', 'isset']);
    }

    /**
     * Option methods that read as "a value is absent" (true → absent).
     *
     * @return list<string>
     */
    private function absentChecks(): array
    {
        return $this->lowerList('absent_checks', ['isnone', 'isabsent']);
    }

    /**
     * Option methods that unwrap the held value (the present-branch accessor).
     *
     * @return list<string>
     */
    private function unwrapAccessors(): array
    {
        return $this->lowerList('unwrap_accessors', ['unwrap', 'expect', 'get', 'value']);
    }

    /**
     * @param  list<string>  $default
     * @return list<string>
     */
    private function lowerList(string $key, array $default): array
    {
        $configured = $this->config($key, $default);

        if (! is_array($configured) || $configured === []) {
            $configured = $default;
        }

        return array_values(array_map(
            strtolower(...),
            array_filter($configured, 'is_string'),
        ));
    }
}
