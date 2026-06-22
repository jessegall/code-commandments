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
                'A state transition is split from its persistence. Either (1) a CALL SITE '
                . 'assigns to a record\'s own attributes (`$order->status = …`) and immediately '
                . 'persists it (`$order->save()`) — a self-referential counter, a closed-set '
                . 'transition, or several fields set together; or (2) the record\'s OWN public '
                . 'behaviour method changes `$this` attributes but never calls `$this->save()`, '
                . 'so callers still have to persist by hand. Strongest when the same change '
                . 'recurs across call sites.'
            )
            ->leaveWhen(
                'A genuine one-off administrative write with no domain meaning and no '
                . 'duplication — a test factory tweak, a migration / backfill script, a truly '
                . 'local single use — or a deliberate UNIT-OF-WORK where many in-memory '
                . 'mutations are intentionally batched into one later `save()` (then the '
                . 'mutate-only methods are correct).'
            )
            ->whenUnsure(
                'Ask "does this represent a NAMED operation the record should own end to end?" '
                . 'If yes, give it a behaviour method (`markShipped()`, `incrementSequenceNumber()`) '
                . 'that owns BOTH the change and its persistence (`$this->save()`), so the call '
                . 'site reads as intent and nothing is left half-applied. If it is a genuine '
                . 'one-off or a batched unit-of-work, leave it.'
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

But moving the mutation onto the record is only HALF the job. If the behaviour
method changes state without persisting it, the caller still has to remember
`$model->incrementSequenceNumber(); $model->save();` — the same scatter, one step
removed:

Bad — a named method that forgets to persist:
    public function incrementSequenceNumber(): void
    {
        $this->edit_seq++;          // mutated, but never saved
    }

Good — the method owns the transition end to end:
    public function incrementSequenceNumber(): void
    {
        $this->edit_seq++;
        $this->save();              // persistence is part of the operation
    }

WHAT FIRES — two halves of the same principle:

  (1) CALL SITE — a run of one-or-more CONSECUTIVE statements that assign to
      `$x->prop` (a property on a plain variable), IMMEDIATELY followed by
      `$x->save()` on the SAME variable. Two sub-signals sharpen the suggestion:
        * SELF-REFERENTIAL counter — `$m->seq = $m->seq + 1` / `$m->count += 1` /
          `$m->seq++` → an `increment…()` / `advance…()` method;
        * CLOSED-SET assignment — `$m->status = SomeEnum::Case` → a `mark…()` /
          `transitionTo…()` method.

  (2) IN MODEL — a PUBLIC behaviour method on a persistable record (one that has,
      inherits, or declares a `save()`) that writes `$this` attributes but never
      calls `$this->save()`. Make the method persist itself.

WHAT DOES NOT — a CALL-SITE assignment not immediately followed by the same
instance's `save()`; a `save()` with no preceding attribute write; a fluent
(`return $this`/`self`) builder setter; a private helper composed by a saving
method; the `save()` method itself; a class with no persistence surface at all.
The persist method is `save` by default and config-overridable (`persist_methods`),
so a different ORM's idiom can anchor it.

It is an ADVISORY warning, not a sin — one-off writes (factories, backfills) and
deliberate unit-of-work batching (many in-memory mutations, one later `save()`)
are legitimate. It is not auto-fixable: extracting the method requires a
human-chosen name, and whether a method should self-persist is a design call.

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

        // The OTHER half: a record's OWN behaviour method that changes persistent
        // state but never persists it, so the caller still has to remember save().
        foreach ($this->unsavedMutationMethods($ast) as $hit) {
            $warnings[] = $this->warningAt(
                $hit['line'],
                $this->methodMessage($hit['class'], $hit['method'], $hit['prop'], $persist[0] ?? 'save'),
                $this->lineAt($content, $hit['line']),
                "mutate-without-save:{$hit['class']}::{$hit['method']}",
            );
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * Public mutation methods on a PERSISTABLE record that change `$this`
     * attributes but never call `$this->save()` — the in-model half of the smell:
     * the behaviour method is named, but it does not own its persistence, so every
     * caller must still remember to save. Fluent (`return $this`/`self`) builders
     * and the persist method itself are exempt.
     *
     * @param  array<Node>  $ast
     * @return list<array{class: string, method: string, prop: string, line: int}>
     */
    private function unsavedMutationMethods(array $ast): array
    {
        $persist = $this->persistMethods();
        $hits = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if ($class->name === null || ! $this->isPersistableRecord($class, $ast, $persist)) {
                continue;
            }

            $className = $class->name->toString();

            foreach ($class->getMethods() as $method) {
                if (! $method->isPublic()
                    || $method->isStatic()
                    || $method->isAbstract()
                    || $method->stmts === null
                    || str_starts_with($method->name->toString(), '__')
                    || in_array(strtolower($method->name->toString()), $persist, true)
                    || $this->isFluent($method)) {
                    continue;
                }

                $prop = $this->firstThisAttributeWrite($method);

                if ($prop === null || $this->callsThisPersist($method, $persist)) {
                    continue;
                }

                $hits[] = [
                    'class' => $className,
                    'method' => $method->name->toString(),
                    'prop' => $prop,
                    'line' => $method->getStartLine(),
                ];
            }
        }

        return $hits;
    }

    /**
     * Whether the class is a persistable active-record: it has (or inherits) a
     * `save()` method (reflection, when the class is autoloadable), or — for code
     * not yet loadable — it DECLARES `save()` or calls `$this->save()` somewhere.
     *
     * @param  array<Node>  $ast
     * @param  list<string>  $persist
     */
    private function isPersistableRecord(Node\Stmt\Class_ $class, array $ast, array $persist): bool
    {
        $fqcn = $this->fqcnOf($class, $ast);

        if ($fqcn !== null && class_exists($fqcn)) {
            foreach ($persist as $name) {
                if (method_exists($fqcn, $name)) {
                    return true;
                }
            }
        }

        // AST fallback: it declares a persist method, or calls $this->persist anywhere.
        foreach ($class->getMethods() as $method) {
            if (in_array(strtolower($method->name->toString()), $persist, true)) {
                return true;
            }

            if ($method->stmts !== null && $this->callsThisPersist($method, $persist)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The first `$this->attr` written (assigned, compound-assigned, or in/decremented)
     * in the method body, or null when the method writes no own attribute.
     */
    private function firstThisAttributeWrite(Node\Stmt\ClassMethod $method): ?string
    {
        foreach ((new NodeFinder)->find($method->stmts, fn (Node $n): bool => $this->isThisAttributeWrite($n)) as $write) {
            /** @var Expr\Assign|Expr\AssignOp|Expr\PostInc|Expr\PreInc|Expr\PostDec|Expr\PreDec $write */
            $target = $write->var;

            if ($target instanceof Expr\PropertyFetch && $target->name instanceof Node\Identifier) {
                return $target->name->toString();
            }
        }

        return null;
    }

    private function isThisAttributeWrite(Node $node): bool
    {
        $target = match (true) {
            $node instanceof Expr\Assign, $node instanceof Expr\AssignOp,
            $node instanceof Expr\PostInc, $node instanceof Expr\PreInc,
            $node instanceof Expr\PostDec, $node instanceof Expr\PreDec => $node->var,
            default => null,
        };

        return $target instanceof Expr\PropertyFetch
            && $target->var instanceof Expr\Variable
            && $target->var->name === 'this'
            && $target->name instanceof Node\Identifier;
    }

    /**
     * Whether the method calls `$this->save()` (any configured persist method).
     *
     * @param  list<string>  $persist
     */
    private function callsThisPersist(Node\Stmt\ClassMethod $method, array $persist): bool
    {
        foreach ((new NodeFinder)->findInstanceOf($method->stmts ?? [], Expr\MethodCall::class) as $call) {
            if ($call->var instanceof Expr\Variable
                && $call->var->name === 'this'
                && $call->name instanceof Node\Identifier
                && in_array(strtolower($call->name->toString()), $persist, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the method is a fluent builder — declares a `self`/`static`/`$this`
     * return type, or returns `$this`. Such mutate-and-return setters are a builder
     * idiom, not the persist-or-not decision this rule is about.
     */
    private function isFluent(Node\Stmt\ClassMethod $method): bool
    {
        $return = $method->returnType;

        if ($return instanceof Node\NullableType) {
            $return = $return->type;
        }

        if (($return instanceof Node\Identifier || $return instanceof Node\Name)
            && in_array(strtolower($return->toString()), ['self', 'static', '$this'], true)) {
            return true;
        }

        foreach ((new NodeFinder)->findInstanceOf($method->stmts ?? [], Node\Stmt\Return_::class) as $ret) {
            if ($ret->expr instanceof Expr\Variable && $ret->expr->name === 'this') {
                return true;
            }
        }

        return false;
    }

    private function methodMessage(string $class, string $method, string $prop, string $persist): string
    {
        return sprintf(
            '`%s::%s()` changes the record\'s persistent state (`$this->%s = …`) but never calls `$this->%s()` — the caller must remember to persist, re-scattering the transition the method was meant to own. Make the behaviour method persist itself: call `$this->%s()` at the end (or, for a deliberate unit-of-work that batches one save, leave it).',
            $class,
            $method,
            $prop,
            $persist,
            $persist,
        );
    }

    /**
     * The fully-qualified name of a class node, using the file's namespace.
     *
     * @param  array<Node>  $ast
     */
    private function fqcnOf(Node\Stmt\Class_ $class, array $ast): ?string
    {
        if ($class->name === null) {
            return null;
        }

        $namespace = (new NodeFinder)->findFirstInstanceOf($ast, Node\Stmt\Namespace_::class);
        $prefix = $namespace?->name?->toString();

        return $prefix !== null ? $prefix . '\\' . $class->name->toString() : $class->name->toString();
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
