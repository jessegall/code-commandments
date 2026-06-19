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
use PhpParser\NodeFinder;
use PhpParser\NodeVisitorAbstract;

/**
 * Flag the imperative diverging guard on an Option —
 * `if ($o->isEmpty()) { return/throw …; } return … $o->getOrThrow() …;` — and
 * suggest the fluent `transform()->orElse()->getOrThrow()` (or
 * `->getOrThrow($onEmpty)`) chain: one expression, exactly one branch, no temp.
 *
 * Complements UnwrapOptionWithGuard (which targets the `$x = $o->getOrThrow()`
 * assignment shape); this targets the two-branch guard whose empty path returns
 * or throws a DIFFERENT thing and whose present path uses getOrThrow() inline.
 */
#[IntroducedIn('1.138.0')]
class PreferOptionChainOverGuardProphet extends PhpCommandment
{
    private const GUARD_METHODS = ['isempty'];

    private const NEGATED_GUARD_METHODS = ['hasvalue'];

    private const UNWRAP_METHODS = ['getorthrow', 'getorfail', 'unwrap'];

    public function description(): string
    {
        return 'Prefer an Option chain over an imperative isEmpty() guard — transform()->orElse()->getOrThrow()';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('An `if ($o->isEmpty()) { return X; }` (or `{ throw … }`) guard is immediately followed by a `return` that unwraps the SAME Option inline with `$o->getOrThrow()`. The two diverging branches map onto `->transform(present)` + `->orElse(empty)` / `->getOrThrow($onEmpty)`.')
            ->leaveWhen('the present-branch (fall-through) body is large or multi-statement with several early exits — forcing it into a `transform()` closure would just trip ShortClosure. Even then, extracting a named method and chaining to it is usually cleaner.')
            ->whenUnsure('if the empty branch RETURNS a value, chain `->orElse(fn () => $value)`; if it THROWS, push the throw into `->getOrThrow(fn () => $exception)`. Keep the manual guard only when both branches do substantial, different work.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A guard that asks an Option "are you empty?", returns/throws one thing if so,
then unwraps it with getOrThrow() on the fall-through is three steps for what the
Option fluent API expresses as one top-to-bottom expression. It also invites the
classic bugs: calling ->getOrThrow() twice, or forgetting the guard entirely.

Bad — early-return-error guard:
    $workflow = $this->findWorkflow($id);

    if ($workflow->isEmpty()) {
        return $this->respondError(sprintf('No workflow found with id "%s".', $id));
    }

    return $this->report($validator, $workflow->getOrThrow()->graph, $workflow->getOrThrow());

Good — chain it (empty branch returns → orElse):
    return $this->findWorkflow($id)
        ->transform(fn (Workflow $w): int => $this->report($validator, $w->graph, $w))
        ->orElse(fn () => $this->respondError(sprintf('No workflow found with id "%s".', $id)))
        ->getOrThrow();

Bad — guard that throws:
    $graph = $this->graphFromFile($file);

    if ($graph->isEmpty()) {
        throw CompileTargetException::unreadableFile($file);
    }

    return $compiler->compileSnapshot($graph->getOrThrow());

Good — push the throw into getOrThrow (no `if` needed):
    return $compiler->compileSnapshot(
        $this->graphFromFile($file)->getOrThrow(fn () => CompileTargetException::unreadableFile($file)),
    );

`Option::getOrThrow(\Closure|\Throwable|null)` already throws the closure's result
when empty, so the empty branch needs no guard at all.

WHAT FIRES — an `if ($o->isEmpty())` (or `if (! $o->hasValue())`) whose body is a
SINGLE `return <value>;` or `throw …;` (no else), immediately followed by a
`return` expression that calls `$o->getOrThrow()` inline on the SAME Option.

WHAT DOES NOT — a void `return;`/continue/break guard (that is
UnwrapOptionWithGuard's shape), a fall-through that does substantial multi-step
work, or an unwrap on a different variable. Those are judgment calls; absolve
with a reason.
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
            public function __construct(private array &$warnings, private PreferOptionChainOverGuardProphet $prophet) {}

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

        for ($i = 0; $i < $count - 1; $i++) {
            $guard = $this->divergingGuard($stmts[$i]);

            if ($guard === null) {
                continue;
            }

            // The very next statement must be a `return` that unwraps the SAME
            // Option inline — the diverging present branch.
            if (! $this->returnsUnwrapping($stmts[$i + 1], $guard['opt'])) {
                continue;
            }

            $suggestion = $guard['kind'] === 'throw'
                ? sprintf('use `%s->getOrThrow(fn () => <exception>)` and drop the guard (getOrThrow throws on empty).', '$' . $guard['opt'])
                : sprintf('chain `->transform(fn ($v) => <present>)->orElse(fn () => <empty>)->getOrThrow()` on $%s.', $guard['opt']);

            $warnings[] = $this->warningAt(
                $stmts[$i]->getStartLine(),
                sprintf(
                    'Imperative `if ($%s->isEmpty()) { … } return … $%s->getOrThrow() …;` guard — %s One expression, exactly one branch, no temp.',
                    $guard['opt'],
                    $guard['opt'],
                    $suggestion,
                ),
                null,
                'option-chain-guard:' . $guard['opt'],
            );
        }
    }

    /**
     * An `if ($o->isEmpty())` / `if (! $o->hasValue())` guard, no else, whose body
     * is a single diverging exit — `return <value>;` or `throw …;` (a void
     * `return;`/continue/break is NOT diverging — that is the sibling's shape).
     *
     * @return array{opt: string, kind: 'return'|'throw'}|null
     */
    private function divergingGuard(Node $stmt): ?array
    {
        if (! $stmt instanceof Node\Stmt\If_ || $stmt->else !== null || $stmt->elseifs !== []
            || count($stmt->stmts) !== 1
        ) {
            return null;
        }

        $opt = $this->emptinessReceiver($stmt->cond);

        if ($opt === null) {
            return null;
        }

        $only = $stmt->stmts[0];

        if ($only instanceof Node\Stmt\Return_ && $only->expr !== null) {
            return ['opt' => $opt, 'kind' => 'return'];
        }

        if ($only instanceof Node\Stmt\Throw_
            || ($only instanceof Node\Stmt\Expression && $only->expr instanceof Node\Expr\Throw_)
        ) {
            return ['opt' => $opt, 'kind' => 'throw'];
        }

        return null;
    }

    private function emptinessReceiver(Node\Expr $cond): ?string
    {
        if ($cond instanceof Node\Expr\MethodCall
            && $cond->name instanceof Node\Identifier
            && in_array(strtolower($cond->name->toString()), self::GUARD_METHODS, true)
        ) {
            return $this->variableName($cond->var);
        }

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
     * Whether $stmt is a `return …;` whose expression unwraps $opt inline with
     * `$opt->getOrThrow()` (at least once).
     */
    private function returnsUnwrapping(Node $stmt, string $opt): bool
    {
        if (! $stmt instanceof Node\Stmt\Return_ || $stmt->expr === null) {
            return false;
        }

        foreach ((new NodeFinder)->findInstanceOf([$stmt->expr], Node\Expr\MethodCall::class) as $call) {
            if ($call->name instanceof Node\Identifier
                && in_array(strtolower($call->name->toString()), self::UNWRAP_METHODS, true)
                && $this->variableName($call->var) === $opt
            ) {
                return true;
            }
        }

        return false;
    }

    private function variableName(Node\Expr $expr): ?string
    {
        return $expr instanceof Node\Expr\Variable && is_string($expr->name) ? $expr->name : null;
    }
}
