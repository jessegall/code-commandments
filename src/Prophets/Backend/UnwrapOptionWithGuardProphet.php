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
use JesseGall\CodeCommandments\Results\Warning;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Flag `if ($o->isEmpty()) { return …; } $x = $o->getOrThrow();` — ask-then-
 * unwrap on an Option that re-implements the combinators Option already ships
 * (getOr / transform / tap). Tell the Option what to do; don't interrogate it.
 */
#[IntroducedIn('1.115.0')]
class UnwrapOptionWithGuardProphet extends PhpCommandment implements SinRepenter
{
    private const GUARD_METHODS = ['isempty'];

    private const NEGATED_GUARD_METHODS = ['hasvalue'];

    private const UNWRAP_METHODS = ['getorthrow', 'getorfail', 'unwrap'];

    /** How many statements after the guard to look for the unwrap. */
    private const LOOKAHEAD = 8;

    public function description(): string
    {
        return 'Do not guard-then-unwrap an Option — use getOr()/transform()/tap() instead of isEmpty() + getOrThrow()';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('An `if ($o->isEmpty()) { return/continue/throw; }` guard is immediately followed by `$x = $o->getOrThrow();` — branching on emptiness and re-unwrapping by hand, which is exactly what Option::getOr()/transform()/tap() express.')
            ->leaveWhen('the guard body does real work beyond an early exit (logs, side effects); the empty branch returns a COMPUTED alternative (an instance method call like `return $this->fallbackFor($x)`) rather than a trivial default — the two absence outcomes then differ and getOr() would evaluate it eagerly; the two branches genuinely diverge in a way transform() cannot express; or the guard and unwrap act on different variables.')
            ->whenUnsure('if the method just needs the value-or-a-default, use getOr($default); if it transforms the value, use transform(); if it runs a side effect, use tap() — only keep the manual guard when both branches do substantial, different work.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Option ships getOr(), transform() and tap() precisely so callers never have to ask
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

Good — transform the value:
    return $option->transform(fn ($resource) => $this->wrap($resource));

Other shapes:
- default fallback:  $x = $opt->isEmpty() ? $default : $opt->getOrThrow();  →  $opt->getOr($default)
- side effect only:  if ($opt->isEmpty()) return; $x = $opt->getOrThrow(); $this->log($x);  →  $opt->tap(fn ($x) => $this->log($x))
- inside a loop:     if ($opt->isEmpty()) { continue; } $x = $opt->getOrThrow();  →  $opt->tap(fn ($x) => …) (or filter the collection upstream)

WHAT FIRES — an `if ($o->isEmpty())` (or `if (! $o->hasValue())`) whose body is a
SINGLE early exit (return / continue / break / throw, no else), followed within a
few statements by `$x = $o->getOrThrow()` on the SAME variable.

WHAT DOES NOT — a guard whose body does more than exit (logging, cleanup); a guard
whose empty branch returns a COMPUTED alternative (`if ($o->isEmpty()) { return
$this->fallbackFor($x); }`) rather than a trivial default, since the two absence
outcomes then differ and the present branch can itself be none() with a distinct
meaning — combinators can't express that, and getOr() would evaluate the alternative
eagerly; a genuine two-way branch that transform() can't model; or an unwrap on a
different variable. Those are judgment calls; absolve with a reason.

AUTO-FIXABLE (the tight shape only): when the guard is `if ($o->isEmpty()) {
return D; }` immediately followed by `$v = $o->getOrThrow();` and `return E;`,
`repent` rewrites the three to `return $o->transform(fn ($v) => E)->getOr(D);` —
behavior-preserving. Looser shapes (continue/break/throw guards, intervening
statements, multi-statement bodies) are left for a human, since the transform/tap
transform there is a judgment call.
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

                // The tight `guard(return) ; $v = unwrap ; return E` triple is
                // mechanically rewritable to ->transform()->getOr(); looser shapes
                // (continue/throw guards, intervening statements) are manual.
                $autoFixable = $this->autoFixableTriple($stmts, $i) !== null;

                $line = $stmts[$i]->getStartLine();
                $warnings[] = $this->warningAt(
                    $line,
                    sprintf(
                        '`if ($%s->isEmpty()) { … } $x = $%s->getOrThrow();` asks-then-unwraps an Option — use $%s->getOr($default), ->transform(fn ($x) => …) or ->tap(fn ($x) => …) instead.',
                        $guardVar,
                        $guardVar,
                        $guardVar,
                    ),
                    null,
                    'unwrap-option-guard:' . $guardVar,
                    $autoFixable,
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

        // Divergent guard clause: the empty branch returns a COMPUTED alternative (an
        // instance method call — e.g. `return $this->triggerSourceFor($node, $graph)`),
        // not a trivial default. The two absence outcomes then differ, and the
        // present branch can itself yield none() with distinct meaning — exactly what
        // the combinators (getOr/orElse/transform) cannot express, since getOr would
        // also evaluate the alternative EAGERLY. Leave it for a human.
        if ($this->returnsComputedAlternative($stmt->stmts[0])) {
            return null;
        }

        return $this->emptinessReceiver($stmt->cond);
    }

    /** Whether the guard's single early exit is `return <instance method call>` — a computed alternative, not a default. */
    private function returnsComputedAlternative(Node\Stmt $stmt): bool
    {
        return $stmt instanceof Node\Stmt\Return_
            && ($stmt->expr instanceof Node\Expr\MethodCall || $stmt->expr instanceof Node\Expr\NullsafeMethodCall);
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

    /**
     * The mechanically-rewritable triple at index $i:
     *   if ($o->isEmpty()) { return D; }   // single-return guard, no else
     *   $v = $o->getOrThrow();             // immediately after
     *   return E;                          // immediately after
     * → `return $o->transform(fn ($v) => E)->getOr(D);`. Returns the parts, or null.
     *
     * @param  array<Node>  $stmts
     * @return array{opt: string, value: string, default: Node\Expr, result: Node\Expr, start: int, end: int}|null
     */
    public function autoFixableTriple(array $stmts, int $i): ?array
    {
        if (! isset($stmts[$i], $stmts[$i + 1], $stmts[$i + 2])) {
            return null;
        }

        $if = $stmts[$i];

        if (! $if instanceof Node\Stmt\If_ || $if->else !== null || $if->elseifs !== []
            || count($if->stmts) !== 1 || ! $if->stmts[0] instanceof Node\Stmt\Return_
            || $if->stmts[0]->expr === null
            || $this->returnsComputedAlternative($if->stmts[0])
        ) {
            return null;
        }

        $opt = $this->emptinessReceiver($if->cond);

        if ($opt === null || ! $this->unwrapsVariable($stmts[$i + 1], $opt)) {
            return null;
        }

        /** @var Node\Expr\Assign $assign */
        $assign = $stmts[$i + 1]->expr;
        $value = $this->variableName($assign->var);

        if ($value === null || ! $stmts[$i + 2] instanceof Node\Stmt\Return_ || $stmts[$i + 2]->expr === null) {
            return null;
        }

        return [
            'opt' => $opt,
            'value' => $value,
            'default' => $if->stmts[0]->expr,
            'result' => $stmts[$i + 2]->expr,
            'start' => (int) $if->getStartFilePos(),
            'end' => (int) $stmts[$i + 2]->getEndFilePos(),
        ];
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

        $edits = [];
        $penance = [];
        $prophet = $this;

        $this->traverse($ast, new class($edits, $penance, $content, $prophet) extends NodeVisitorAbstract {
            /**
             * @param  list<array{start: int, end: int, text: string}>  $edits
             * @param  list<string>  $penance
             */
            public function __construct(
                private array &$edits,
                private array &$penance,
                private string $content,
                private UnwrapOptionWithGuardProphet $prophet,
            ) {}

            public function enterNode(Node $node): ?int
            {
                if (! isset($node->stmts) || ! is_array($node->stmts)) {
                    return null;
                }

                $count = count($node->stmts);

                for ($i = 0; $i < $count; $i++) {
                    $triple = $this->prophet->autoFixableTriple($node->stmts, $i);

                    if ($triple === null) {
                        continue;
                    }

                    $this->edits[] = [
                        'start' => $triple['start'],
                        'end' => $triple['end'],
                        'text' => sprintf(
                            'return $%s->transform(fn ($%s) => %s)->getOr(%s);',
                            $triple['opt'],
                            $triple['value'],
                            $this->slice($triple['result']),
                            $this->slice($triple['default']),
                        ),
                    ];
                    $this->penance[] = sprintf('Rewrote $%s isEmpty()-guard + getOrThrow() to ->transform()->getOr()', $triple['opt']);
                }

                return null;
            }

            private function slice(Node\Expr $expr): string
            {
                $start = (int) $expr->getStartFilePos();

                return substr($this->content, $start, (int) $expr->getEndFilePos() - $start + 1);
            }
        });

        if ($edits === []) {
            return RepentanceResult::unchanged();
        }

        usort($edits, fn ($a, $b) => $b['start'] <=> $a['start']);

        foreach ($edits as $edit) {
            $content = substr($content, 0, $edit['start']) . $edit['text'] . substr($content, $edit['end'] + 1);
        }

        return RepentanceResult::absolved($content, $penance);
    }
}
