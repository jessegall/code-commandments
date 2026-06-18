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
use PhpParser\NodeVisitorAbstract;

/**
 * Flag `if ($o->isEmpty()) { return …; } $x = $o->getOrThrow();` — ask-then-
 * unwrap on an Option that re-implements the combinators Option already ships
 * (getOr / map / each). Tell the Option what to do; don't interrogate it.
 */
#[IntroducedIn('1.115.0')]
class UnwrapOptionWithGuardProphet extends PhpCommandment
{
    private const GUARD_METHODS = ['isempty'];

    private const NEGATED_GUARD_METHODS = ['hasvalue'];

    private const UNWRAP_METHODS = ['getorthrow', 'getorfail', 'unwrap'];

    /** How many statements after the guard to look for the unwrap. */
    private const LOOKAHEAD = 8;

    public function description(): string
    {
        return 'Do not guard-then-unwrap an Option — use getOr()/map()/each() instead of isEmpty() + getOrThrow()';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('An `if ($o->isEmpty()) { return/continue/throw; }` guard is immediately followed by `$x = $o->getOrThrow();` — branching on emptiness and re-unwrapping by hand, which is exactly what Option::getOr()/map()/each() express.')
            ->leaveWhen('the guard body does real work beyond an early exit (logs, side effects), the two branches genuinely diverge in a way map() cannot express, or the guard and unwrap act on different variables.')
            ->whenUnsure('if the method just needs the value-or-a-default, use getOr($default); if it transforms the value, use map(); if it runs a side effect, use each() — only keep the manual guard when both branches do substantial, different work.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Option ships getOr(), map() and each() precisely so callers never have to ask
"are you empty?" and then unwrap. The shape

    if ($node->isEmpty()) {
        return ControlSockets::OUT;
    }
    $descriptor = $node->getOrThrow();

re-implements getOr() by hand: it asks, branches, and unwraps — three steps for
what the Option already does in one. Tell the Option what to do instead.

Bad — ask, branch, unwrap:
    if ($option->isEmpty()) {
        return Option::none();
    }
    $resource = $option->getOrThrow();

    return $this->wrap($resource);

Good — map over the value:
    return $option->map(fn ($resource) => $this->wrap($resource));

Other shapes:
- default fallback:  $x = $opt->isEmpty() ? $default : $opt->getOrThrow();  →  $opt->getOr($default)
- side effect only:  if ($opt->isEmpty()) return; $x = $opt->getOrThrow(); $this->log($x);  →  $opt->each(fn ($x) => $this->log($x))
- inside a loop:     if ($opt->isEmpty()) { continue; } $x = $opt->getOrThrow();  →  $opt->each(fn ($x) => …) (or filter the collection upstream)

WHAT FIRES — an `if ($o->isEmpty())` (or `if (! $o->hasValue())`) whose body is a
SINGLE early exit (return / continue / break / throw, no else), followed within a
few statements by `$x = $o->getOrThrow()` on the SAME variable.

WHAT DOES NOT — a guard whose body does more than exit (logging, cleanup), a
genuine two-way branch that map() can't model, or an unwrap on a different
variable. Those are judgment calls; absolve with a reason.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $warnings = [];
        $prophet = $this;

        $this->traverse($ast, new class($warnings, $prophet) extends NodeVisitorAbstract {
            /** @param list<Warning> $warnings */
            public function __construct(private array &$warnings, private UnwrapOptionWithGuardProphet $prophet) {}

            public function enterNode(Node $node): ?int
            {
                if (isset($node->stmts) && is_array($node->stmts)) {
                    $this->prophet->scanStatements($node->stmts, $this->warnings);
                }

                return null;
            }
        });

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * @param  array<Node\Stmt>  $stmts
     * @param  list<Warning>  $warnings
     */
    public function scanStatements(array $stmts, array &$warnings): void
    {
        $count = count($stmts);

        for ($i = 0; $i < $count; $i++) {
            $guardVar = $this->guardVariable($stmts[$i]);

            if ($guardVar === null) {
                continue;
            }

            $limit = min($count, $i + 1 + self::LOOKAHEAD);

            for ($j = $i + 1; $j < $limit; $j++) {
                if (! $this->unwrapsVariable($stmts[$j], $guardVar)) {
                    continue;
                }

                $line = $stmts[$i]->getStartLine();
                $warnings[] = $this->warningAt(
                    $line,
                    sprintf(
                        '`if ($%s->isEmpty()) { … } $x = $%s->getOrThrow();` asks-then-unwraps an Option — use $%s->getOr($default), ->map(fn ($x) => …) or ->each(fn ($x) => …) instead.',
                        $guardVar,
                        $guardVar,
                        $guardVar,
                    ),
                    null,
                    'unwrap-option-guard:' . $guardVar,
                );

                break;
            }
        }
    }

    /**
     * The Option variable name when $stmt is an `if ($o->isEmpty())` /
     * `if (! $o->hasValue())` whose body is a single early exit; else null.
     */
    private function guardVariable(Node $stmt): ?string
    {
        if (! $stmt instanceof Node\Stmt\If_ || $stmt->else !== null || $stmt->elseifs !== []) {
            return null;
        }

        if (! $this->isSingleEarlyExit($stmt->stmts)) {
            return null;
        }

        return $this->emptinessReceiver($stmt->cond);
    }

    /**
     * @param  array<Node\Stmt>  $stmts
     */
    private function isSingleEarlyExit(array $stmts): bool
    {
        if (count($stmts) !== 1) {
            return false;
        }

        $only = $stmts[0];

        if ($only instanceof Node\Stmt\Return_ || $only instanceof Node\Stmt\Continue_ || $only instanceof Node\Stmt\Break_ || $only instanceof Node\Stmt\Throw_) {
            return true;
        }

        return $only instanceof Node\Stmt\Expression && $only->expr instanceof Node\Expr\Throw_;
    }

    private function emptinessReceiver(Node\Expr $cond): ?string
    {
        // $o->isEmpty()
        if ($cond instanceof Node\Expr\MethodCall
            && $cond->name instanceof Node\Identifier
            && in_array(strtolower($cond->name->toString()), self::GUARD_METHODS, true)
        ) {
            return $this->variableName($cond->var);
        }

        // ! $o->hasValue()
        if ($cond instanceof Node\Expr\BooleanNot
            && $cond->expr instanceof Node\Expr\MethodCall
            && $cond->expr->name instanceof Node\Identifier
            && in_array(strtolower($cond->expr->name->toString()), self::NEGATED_GUARD_METHODS, true)
        ) {
            return $this->variableName($cond->expr->var);
        }

        return null;
    }

    /**
     * Whether $stmt is `$x = $var->getOrThrow()` — unwrapping the same Option.
     */
    private function unwrapsVariable(Node $stmt, string $var): bool
    {
        if (! $stmt instanceof Node\Stmt\Expression || ! $stmt->expr instanceof Node\Expr\Assign) {
            return false;
        }

        $rhs = $stmt->expr->expr;

        return $rhs instanceof Node\Expr\MethodCall
            && $rhs->name instanceof Node\Identifier
            && in_array(strtolower($rhs->name->toString()), self::UNWRAP_METHODS, true)
            && $this->variableName($rhs->var) === $var;
    }

    private function variableName(Node\Expr $expr): ?string
    {
        return $expr instanceof Node\Expr\Variable && is_string($expr->name) ? $expr->name : null;
    }
}
