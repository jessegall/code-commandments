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
use PhpParser\NodeVisitorAbstract;

/**
 * The anemic-model / tell-don't-ask smell (#192): a call site reaches into a
 * record, pokes one or more of its own attributes, and immediately persists it.
 *
 *     $workflow->edit_seq = $workflow->edit_seq + 1;   // self-referential counter
 *     $workflow->save();
 *
 *     $order->status = OrderStatus::Shipped;            // closed-set state transition
 *     $order->save();
 *
 *     $user->verified_at = now();                       // a cohesive multi-field transition
 *     $user->verification_token = null;
 *     $user->save();
 *
 * The MEANING and the INVARIANTS of the state change live in the caller, not the
 * record, so the same mutation gets scattered and duplicated across call sites
 * (the reporter's `edit_seq` was hand-incremented in two places before they
 * noticed) and there is no single place to enforce related rules ("advancing the
 * sequence must also stamp `dispatched_at`"). The remedy is an intention-revealing
 * behaviour method that OWNS the transition:
 *
 *     $workflow->incrementSequenceNumber();
 *     $order->markShipped();
 *
 * Detected purely by SHAPE (no name list classifying "is this a model"): a run of
 * one-or-more consecutive statements assigning to `$x->prop`, immediately followed
 * by `$x->save()` on the SAME variable. The persist call (`save` by default,
 * config-overridable) is the ActiveRecord idiom that anchors the pattern — a
 * mutation that ends in `->save()` is a persisted state change. Writes through
 * `$this` are exempt: code already INSIDE the record owning its behaviour is the
 * destination, not the smell.
 *
 * Advisory, never a sin; not auto-fixable — extracting the method needs a
 * human-chosen name and a decision about whether it also persists.
 */
#[IntroducedIn('2.34.0')]
class EncapsulateModelMutationProphet extends PhpCommandment
{
    /** Persist method names that anchor the pattern (ActiveRecord `save()` by default). */
    private const DEFAULT_PERSIST_METHODS = ['save'];

    public function description(): string
    {
        return 'Flag direct model attribute writes followed by save() — encapsulate the change as a named behaviour method on the model';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A call site assigns to one or more of a record\'s OWN attributes '
                . '(`$order->status = …`) and immediately persists it (`$order->save()`), '
                . 'and that write represents a meaningful state change — a self-referential '
                . 'counter increment (`$m->seq = $m->seq + 1`), a closed-set transition '
                . '(`$m->status = Status::Shipped`), or several related fields set together '
                . 'before the save. Strongest when the SAME field is mutated the same way in '
                . 'more than one call site.'
            )
            ->leaveWhen(
                'A genuine one-off administrative write with no domain meaning and no '
                . 'duplication — a test factory tweak, a migration / backfill script, a '
                . 'truly local single use — or the surrounding code is already INSIDE the '
                . 'record itself (writes through `$this` are not flagged).'
            )
            ->whenUnsure(
                'Ask "does this assignment represent a NAMED operation the record should '
                . 'own (and might be repeated)?" If yes, extract a behaviour method '
                . '(`markShipped()`, `incrementSequenceNumber()`) that owns the change and '
                . 'its invariants — the call site then reads as intent, not mechanics. If '
                . 'it is a genuine one-off, leave it.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Reaching into a record, poking its attributes, then calling `->save()` is the
classic ANEMIC-MODEL / tell-don't-ask smell: the meaning and the invariants of a
state transition live in the CALLER, not the record. The same mutation scatters
and duplicates across call sites, and there is no single place to enforce related
rules.

Bad — the transition's logic lives at the call site, ripe to diverge:
    // EditorActionDispatcher.php
    $workflow->edit_seq = $workflow->edit_seq + 1;
    $workflow->save();

    // WorkflowUpdateController.php  ← the SAME increment, written again
    $workflow->edit_seq = $workflow->edit_seq + 1;
    $workflow->save();

Good — one named behaviour method is the single source of truth:
    // Workflow.php
    public function incrementSequenceNumber(): void
    {
        $this->edit_seq++;
        $this->dispatched_at = now();   // the invariant lives WITH the transition
        $this->save();
    }

    // both call sites
    $workflow->incrementSequenceNumber();

WHAT FIRES — a run of one-or-more CONSECUTIVE statements that assign to `$x->prop`
(a property on a plain variable), IMMEDIATELY followed by `$x->save()` on the SAME
variable. Two sub-signals sharpen the suggestion:
  * SELF-REFERENTIAL counter — `$m->seq = $m->seq + 1` / `$m->count += 1` → an
    `increment…()` / `advance…()` method;
  * CLOSED-SET assignment — `$m->status = SomeEnum::Case` → a `mark…()` /
    `transitionTo…()` method.

WHAT DOES NOT — a write through `$this` (you are already inside the record, which
is exactly where the behaviour method belongs); an assignment NOT immediately
followed by the same instance's `save()`; a `save()` with no preceding attribute
write on the same variable. The persist method is `save` by default and
config-overridable (`persist_methods`), so a different ORM's idiom can anchor it.

It is an ADVISORY warning, not a sin — plenty of legitimate one-off writes exist
(factories, backfills). It is not auto-fixable: extracting the method requires a
human-chosen name and a decision about whether it also calls `save()`.

Configure via:

    Backend\EncapsulateModelMutationProphet::class => [
        'persist_methods' => ['save', 'store'],
    ],
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $persist = $this->persistMethods();
        $warnings = [];

        foreach ($this->statementLists($ast) as $stmts) {
            $count = count($stmts);

            for ($i = 0; $i < $count; $i++) {
                $var = $this->persistTargetVar($stmts[$i], $persist);

                if ($var === null) {
                    continue;
                }

                // Walk backwards over the run of consecutive attribute writes on
                // the same variable that directly precede the save().
                $assignments = [];

                for ($j = $i - 1; $j >= 0; $j--) {
                    $assignment = $this->attributeWriteOn($stmts[$j], $var);

                    if ($assignment === null) {
                        break;
                    }

                    $assignments[] = $assignment;
                }

                if ($assignments === []) {
                    continue;
                }

                // $assignments is newest-first; the first written field (top of the
                // run) is the most readable anchor for the message + fingerprint.
                $assignments = array_reverse($assignments);
                $firstProp = $assignments[0]['prop'];
                $selfRef = $this->any($assignments, 'self');
                $enum = $this->any($assignments, 'enum');
                $multi = count($assignments) > 1;

                $persistMethod = $this->persistMethodOf($stmts[$i]);

                $warnings[] = $this->warningAt(
                    $stmts[$i]->getStartLine(),
                    $this->message($var, $firstProp, $persistMethod, $selfRef, $enum, $multi),
                    $this->lineAt($content, $stmts[$i]->getStartLine()),
                    "mutate-then-save:\${$var}->{$firstProp}",
                );
            }
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    private function message(string $var, string $prop, string $persist, bool $selfRef, bool $enum, bool $multi): string
    {
        $head = $multi
            ? sprintf('Several attributes of `$%s` are assigned and then immediately persisted with `$%s->%s()`', $var, $var, $persist)
            : sprintf('`$%s->%s` is assigned and then immediately persisted with `$%s->%s()`', $var, $prop, $var, $persist);

        if ($selfRef) {
            $remedy = sprintf('This self-referential counter is duplication waiting to diverge. Move it onto the record as a named method (e.g. `$%s->increment%s()`) that owns the increment and any invariant.', $var, ucfirst($this->camel($prop)));
        } elseif ($enum) {
            $remedy = sprintf('This closed-set state transition belongs on the record as a named method (e.g. `$%s->mark…()` / `$%s->transitionTo…()`) so the transition and its rules live in one place.', $var, $var);
        } else {
            $remedy = sprintf('Extract a named behaviour method onto the record (e.g. `$%s->…()`) that owns this change (and optionally the `%s()`), so the call site reads as intent and the change is not scattered across callers.', $var, $persist);
        }

        return $head . ' at the call site — the anemic-model / tell-don\'t-ask smell. ' . $remedy;
    }

    /**
     * The variable a persist call is made on (`$x->save()` → `x`), or null when
     * this statement is not a persist call on a plain, non-`$this` variable.
     *
     * @param  list<string>  $persist
     */
    private function persistTargetVar(Node\Stmt $stmt, array $persist): ?string
    {
        if (! $stmt instanceof Node\Stmt\Expression || ! $stmt->expr instanceof Expr\MethodCall) {
            return null;
        }

        $call = $stmt->expr;

        if (! $call->name instanceof Node\Identifier
            || ! in_array(strtolower($call->name->toString()), $persist, true)) {
            return null;
        }

        if (! $call->var instanceof Expr\Variable || ! is_string($call->var->name) || $call->var->name === 'this') {
            return null;
        }

        return $call->var->name;
    }

    private function persistMethodOf(Node\Stmt $stmt): string
    {
        if ($stmt instanceof Node\Stmt\Expression
            && $stmt->expr instanceof Expr\MethodCall
            && $stmt->expr->name instanceof Node\Identifier) {
            return $stmt->expr->name->toString();
        }

        return 'save';
    }

    /**
     * If this statement assigns to `$var->prop`, return the property name plus
     * whether it is a self-referential counter or a closed-set (enum) assignment.
     * Null otherwise — which terminates the backward run.
     *
     * @return array{prop: string, self: bool, enum: bool}|null
     */
    private function attributeWriteOn(Node\Stmt $stmt, string $var): ?array
    {
        if (! $stmt instanceof Node\Stmt\Expression) {
            return null;
        }

        $expr = $stmt->expr;

        // `$m->seq++` / `++$m->seq` / `$m->seq--` are counter writes too.
        if ($expr instanceof Expr\PostInc || $expr instanceof Expr\PreInc
            || $expr instanceof Expr\PostDec || $expr instanceof Expr\PreDec) {
            $target = $expr->var;

            if ($target instanceof Expr\PropertyFetch
                && $target->var instanceof Expr\Variable
                && $target->var->name === $var
                && $target->name instanceof Node\Identifier) {
                return ['prop' => $target->name->toString(), 'self' => true, 'enum' => false];
            }

            return null;
        }

        if (! $expr instanceof Expr\Assign && ! $expr instanceof Expr\AssignOp) {
            return null;
        }

        $target = $expr->var;

        if (! $target instanceof Expr\PropertyFetch
            || ! $target->var instanceof Expr\Variable
            || $target->var->name !== $var
            || ! $target->name instanceof Node\Identifier) {
            return null;
        }

        $prop = $target->name->toString();

        return [
            'prop' => $prop,
            'self' => $this->isSelfReferential($expr, $var, $prop),
            'enum' => $expr instanceof Expr\Assign && $this->isClosedSetValue($expr->expr),
        ];
    }

    /**
     * A compound assignment (`+=`, `-=`, …) on the attribute, or a plain
     * assignment whose right-hand side reads the SAME attribute back — both are
     * counter / accumulator mutations.
     */
    private function isSelfReferential(Expr $expr, string $var, string $prop): bool
    {
        if ($expr instanceof Expr\AssignOp) {
            return true;
        }

        if (! $expr instanceof Expr\Assign) {
            return false;
        }

        foreach ((new NodeFinder)->findInstanceOf([$expr->expr], Expr\PropertyFetch::class) as $fetch) {
            if ($fetch->var instanceof Expr\Variable
                && $fetch->var->name === $var
                && $fetch->name instanceof Node\Identifier
                && $fetch->name->toString() === $prop) {
                return true;
            }
        }

        return false;
    }

    /**
     * A closed-set value: a class-constant / enum-case fetch (`Status::Shipped`),
     * but not a `::class` magic constant (that is a class-string, not a state).
     */
    private function isClosedSetValue(Expr $value): bool
    {
        return $value instanceof Expr\ClassConstFetch
            && $value->name instanceof Node\Identifier
            && strtolower($value->name->toString()) !== 'class';
    }

    /**
     * Every statement list in the file: the top-level statements plus the body of
     * every node that carries a `stmts` array (methods, closures, if/else, loops,
     * try/catch, switch cases, …). A run-of-statements pattern is scoped to a
     * single block, so we scan each block's list independently.
     *
     * @param  array<Node>  $ast
     * @return list<array<Node\Stmt>>
     */
    private function statementLists(array $ast): array
    {
        $collector = new class extends NodeVisitorAbstract {
            /** @var list<array<Node\Stmt>> */
            public array $lists = [];

            public function enterNode(Node $node): ?int
            {
                if (isset($node->stmts) && is_array($node->stmts)) {
                    $this->lists[] = $node->stmts;
                }

                return null;
            }
        };

        $this->traverse($ast, $collector);

        return [$ast, ...$collector->lists];
    }

    /**
     * @param  list<array{prop: string, self: bool, enum: bool}>  $assignments
     */
    private function any(array $assignments, string $key): bool
    {
        foreach ($assignments as $assignment) {
            if ($assignment[$key]) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function persistMethods(): array
    {
        $configured = $this->config('persist_methods', self::DEFAULT_PERSIST_METHODS);

        if (! is_array($configured)) {
            return self::DEFAULT_PERSIST_METHODS;
        }

        $methods = array_values(array_map(
            static fn (string $m): string => strtolower($m),
            array_filter($configured, 'is_string'),
        ));

        return $methods === [] ? self::DEFAULT_PERSIST_METHODS : $methods;
    }

    private function camel(string $snake): string
    {
        $words = preg_split('/[^a-zA-Z0-9]+/', $snake) ?: [$snake];
        $words = array_values(array_filter($words));

        if ($words === []) {
            return $snake;
        }

        $first = strtolower($words[0]);
        $rest = array_map(static fn (string $w): string => ucfirst(strtolower($w)), array_slice($words, 1));

        return $first . implode('', $rest);
    }

    private function lineAt(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return trim($lines[$line - 1] ?? '');
    }
}
