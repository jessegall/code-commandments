<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Flag a PRIVATE method that declares a non-void return type but whose result is
 * DISCARDED at every call site — the value it computes is dead. Either the method
 * should be `void` (and stop computing a return), or its callers forgot to use what
 * it produces.
 *
 * In-class census (a private method's every caller is visible in its own class — no
 * call graph needed): all `$this->m(...)` calls are bare expression statements, none
 * assigns/returns/passes the result. Forward-consumption family of #163. Near-zero-FP:
 * private scope (callers fully visible), a declared value-returning type, ≥1 call,
 * and not referenced as a callable. ADVISORY (a WARNING). GENERIC: pure AST.
 */
#[IntroducedIn('2.18.0')]
class DeadProducerProphet extends PhpCommandment
{
    private const VOIDISH = ['void', 'never', 'self', 'static'];

    public function description(): string
    {
        return 'A private method that returns a value nobody uses should be void (or its callers should use the result)';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A PRIVATE method declares a non-void return type, is called at least once '
                . 'in its class, and EVERY call discards the result (a bare `$this->m();` '
                . 'statement — never assigned, returned, or passed on). The computed value '
                . 'is dead.'
            )
            ->leaveWhen(
                'the method is part of a fluent chain (returns $this/self/static); it is '
                . 'public/protected (callers outside the class may use the result); it is '
                . 'referenced as a callable; or the return exists for an interface/override '
                . 'contract.'
            )
            ->whenUnsure(
                'if the value is genuinely unused, change the return type to `void` and drop '
                . 'the `return <expr>` (keep only the side effect); if it IS meant to be used, '
                . 'the bug is at the call sites — capture and use the result.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A method that computes and returns a value which every caller throws away is doing
dead work — the `return` misleads readers into thinking the result matters, and the
computation may be wasted.

Bad — `format()` returns a string, but every call ignores it:
    private function format(Row $r): string { return $this->cache[$r->id] = render($r); }
    // calls: $this->format($a);  $this->format($b);   // result discarded everywhere

Good — say what it does (a side effect), return nothing:
    private function cache(Row $r): void { $this->cache[$r->id] = render($r); }

…or, if the value was meant to be used, fix the call sites to capture it.

WHAT FIRES — a PRIVATE method with a declared non-void/non-fluent return type, called
≥1 time in its class, where every `$this->m(...)` call is a bare expression statement.

WHAT DOES NOT — public/protected methods (external callers unseen), fluent
return-$this methods, methods referenced as a callable, or methods with no in-class
call. Advisory (a WARNING); not auto-fixable.
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

        foreach ($finder->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            foreach ($class->getMethods() as $method) {
                if (! $this->isValueReturningPrivate($method)) {
                    continue;
                }

                $name = $method->name->toString();

                if ($this->isReferencedAsCallable($class, $name, $finder)) {
                    continue;
                }

                [$calls, $allDiscarded] = $this->callConsumption($class, $name, $finder);

                if ($calls >= 1 && $allDiscarded) {
                    $warnings[] = $this->warningAt(
                        $method->getStartLine(),
                        sprintf(
                            'Private method `%s()` declares a non-void return type, but every one of its %d call site(s) discards the result (`$this->%s(...);` as a bare statement). The returned value is dead — make `%s()` return `void` and drop the `return <expr>` (keep the side effect), or fix the call sites to use what it produces.',
                            $name,
                            $calls,
                            $name,
                            $name,
                        ),
                        null,
                        'dead-producer:' . $name,
                    );
                }
            }
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    private function isValueReturningPrivate(Node\Stmt\ClassMethod $method): bool
    {
        if (! $method->isPrivate() || $method->isAbstract() || $method->stmts === null) {
            return false;
        }

        if (str_starts_with($method->name->toString(), '__')) {
            return false; // magic methods
        }

        $type = $method->returnType;

        if ($type === null) {
            return false; // no declared return type — ambiguous, leave it
        }

        if ($type instanceof Node\NullableType) {
            return true; // `?T` always produces a value-or-null
        }

        // A union/intersection return is never void → it produces a value.
        if ($type instanceof Node\UnionType || $type instanceof Node\IntersectionType) {
            return true;
        }

        // Built-in types are Identifier (string/int/void/never); class/self/static are Name.
        $name = match (true) {
            $type instanceof Node\Identifier => strtolower($type->toString()),
            $type instanceof Node\Name => strtolower($type->getLast()),
            default => 'void',
        };

        return ! in_array($name, self::VOIDISH, true);
    }

    /**
     * @return array{0: int, 1: bool} [number of in-class calls, whether all discard the result]
     */
    private function callConsumption(Node\Stmt\Class_ $class, string $name, NodeFinder $finder): array
    {
        $calls = [];

        foreach ($class->getMethods() as $method) {
            if ($method->stmts === null) {
                continue;
            }

            foreach ($finder->findInstanceOf($method->stmts, Node\Expr\MethodCall::class) as $call) {
                if ($this->isThisCall($call, $name)) {
                    $calls[spl_object_id($call)] = $call;
                }
            }
        }

        if ($calls === []) {
            return [0, false];
        }

        // A call is "used" unless it is the whole expression of an expression statement.
        $discarded = [];

        foreach ($class->getMethods() as $method) {
            if ($method->stmts === null) {
                continue;
            }

            foreach ($finder->findInstanceOf($method->stmts, Node\Stmt\Expression::class) as $stmt) {
                if ($stmt->expr instanceof Node\Expr\MethodCall && isset($calls[spl_object_id($stmt->expr)])) {
                    $discarded[spl_object_id($stmt->expr)] = true;
                }
            }
        }

        return [count($calls), count($discarded) === count($calls)];
    }

    private function isThisCall(Node\Expr\MethodCall $call, string $name): bool
    {
        return ! $call->isFirstClassCallable()
            && $call->var instanceof Node\Expr\Variable
            && $call->var->name === 'this'
            && $call->name instanceof Node\Identifier
            && $call->name->toString() === $name;
    }

    /** Whether the method name is used as a callable reference ($this->m(...), [$this,'m'], 'm'). */
    private function isReferencedAsCallable(Node\Stmt\Class_ $class, string $name, NodeFinder $finder): bool
    {
        // First-class callable `$this->m(...)`.
        foreach ($finder->findInstanceOf($class, Node\Expr\MethodCall::class) as $call) {
            if ($call->isFirstClassCallable()
                && $call->var instanceof Node\Expr\Variable
                && $call->var->name === 'this'
                && $call->name instanceof Node\Identifier
                && $call->name->toString() === $name
            ) {
                return true;
            }
        }

        // String reference to the method name anywhere (`[$this, 'm']`, `'m'`) — conservative.
        foreach ($finder->findInstanceOf($class, Node\Scalar\String_::class) as $string) {
            if ($string->value === $name) {
                return true;
            }
        }

        return false;
    }
}
