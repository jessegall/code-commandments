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
 * Flag a private method that returns `?T` / `Option<T>` when EVERY call site
 * substitutes the SAME shape of fixed fallback (`$this->m(...) ?? <const>` or
 * `$this->m(...)->getOr(<const>)`) — and nudge a `$default` PARAMETER instead.
 *
 * When the absence is always filled with a constant at the call site, the
 * maybe-return is ceremony: the method may as well take a `$default` and return a
 * plain `T`, so callers read `$this->m($x, '0')` instead of `$this->m($x) ?? '0'`.
 *
 * Scoped to PRIVATE methods on purpose: all their call sites live in the same
 * class, so "EVERY caller defaults with a constant" is provable from one file — no
 * call-graph, no cross-file uncertainty (which is where this kind of rule breeds
 * false positives). ADVISORY (a WARNING) — it never blocks. GENERIC: pure AST.
 */
#[IntroducedIn('2.12.0')]
class PreferDefaultOverNullableProphet extends PhpCommandment
{
    private const OPTION_GETOR = 'getor';

    public function description(): string
    {
        return 'Prefer a $default parameter over a nullable/Option when every caller substitutes a fixed fallback';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A PRIVATE method returns `?T` / `T | null` / `Option<T>`, and EVERY one '
                . 'of its in-class call sites immediately substitutes a fixed constant for '
                . 'the absent case (`$this->m(...) ?? \'0\'` or `$this->m(...)->getOr(0)`). '
                . 'The maybe-return is ceremony — the fallback is always the same.'
            )
            ->leaveWhen(
                'a caller branches on the absence, rethrows, maps it, or substitutes a '
                . 'NON-constant (a computed value, another call); the absence is genuinely '
                . 'handled differently per site; or the method is not private (its callers '
                . 'are not all visible here). Then the maybe-return is carrying real info.'
            )
            ->whenUnsure(
                'if the fallback is always the same constant, give the method a `$default` '
                . 'parameter (defaulting to that constant) and return plain `T`; callers '
                . 'pass the default (or rely on it) and drop the `?? …` / `->getOr(…)`. If '
                . 'absence is handled differently per caller, keep the Option.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A `?T` / `Option<T>` return says "there might be no value, you decide what to do".
But when EVERY caller decides the same thing — substitute one fixed constant — the
decision belongs IN the method as a `$default` parameter, not re-spelled at every
call site.

Bad — the method returns a maybe, every caller fills it with the same constant:
    private function headerValue(string $head, HttpHeader $h): ?string { … return null; }
    // callers:
    $len = (int) ($this->headerValue($head, HttpHeader::ContentLength) ?? '0');
    $key = $this->headerValue($head, HttpHeader::SecWebSocketKey) ?? '';

Good — a $default parameter, plain T return:
    private function headerValue(string $head, HttpHeader $h, string $default = ''): string
    {
        … return $default;   // when absent
    }
    $len = (int) $this->headerValue($head, HttpHeader::ContentLength, '0');
    $key = $this->headerValue($head, HttpHeader::SecWebSocketKey);

WHAT FIRES — a PRIVATE method returning `?T` / `T | null` / `Option<T>` with >= 1
in-class call site, where EVERY call site is `$this->m(...) ?? <const>` (nullable)
or `$this->m(...)->getOr(<const>)` (Option), the constant being a scalar literal
or a class constant.

WHAT DOES NOT — a caller that branches/rethrows/maps the absence, or substitutes a
non-constant; a `?? null` (not a real default); a non-private method (callers not
all visible); a method with no call sites. Advisory (a WARNING); not auto-fixable
(adding a parameter + retyping changes the signature).
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
                if (! $method->isPrivate() || $method->isStatic()) {
                    continue;
                }

                $kind = $this->returnKind($method);

                if ($kind === null) {
                    continue;
                }

                $name = $method->name->toString();
                $calls = $this->thisCalls($finder, $class, $name);

                if ($calls === []) {
                    continue;
                }

                $defaulted = $this->defaultedCallIds($finder, $class, $name, $kind);

                foreach ($calls as $call) {
                    if (! isset($defaulted[spl_object_id($call)])) {
                        continue 2; // a non-defaulting call site → not the pattern
                    }
                }

                $warnings[] = $this->warningAt(
                    $method->getStartLine(),
                    sprintf(
                        'Private method %s() returns %s, but every one of its %d call site%s immediately substitutes a fixed constant for the absent case (%s). The fallback is always the same — give %s() a `$default` parameter (defaulting to that constant) and return plain T, so callers pass the default instead of re-spelling `%s` at every call. Advisory; not auto-fixed (it changes the signature).',
                        $name,
                        $kind === 'option' ? 'an `Option<T>`' : 'a nullable',
                        count($calls),
                        count($calls) === 1 ? '' : 's',
                        $kind === 'option' ? '`->getOr(<const>)`' : '`?? <const>`',
                        $name,
                        $kind === 'option' ? '->getOr(…)' : '?? …',
                    ),
                    null,
                    'prefer-default:' . $name,
                );
            }
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /** 'nullable' / 'option' for a maybe-returning method, else null. */
    private function returnKind(Node\Stmt\ClassMethod $method): ?string
    {
        $type = $method->returnType;

        if ($type instanceof Node\NullableType) {
            return 'nullable';
        }

        if ($type instanceof Node\UnionType) {
            foreach ($type->types as $member) {
                if ($member instanceof Node\Identifier && strtolower($member->toString()) === 'null') {
                    return 'nullable';
                }
            }

            return null;
        }

        if ($type instanceof Node\Name && $type->getLast() === 'Option') {
            return 'option';
        }

        return null;
    }

    /**
     * Every `$this->{name}(...)` method-call node in the class.
     *
     * @return list<Node\Expr\MethodCall>
     */
    private function thisCalls(NodeFinder $finder, Node\Stmt\Class_ $class, string $name): array
    {
        $calls = [];

        foreach ($finder->findInstanceOf($class, Node\Expr\MethodCall::class) as $call) {
            if ($call->var instanceof Node\Expr\Variable
                && $call->var->name === 'this'
                && $call->name instanceof Node\Identifier
                && $call->name->toString() === $name
            ) {
                $calls[] = $call;
            }
        }

        return $calls;
    }

    /**
     * The spl_object_ids of the `$this->{name}(...)` call nodes that ARE consumed by
     * substituting a fixed constant for the absent case — `… ?? <const>` (nullable)
     * or `…->getOr(<const>)` (option). Found by matching consumption sites back to
     * the call node by identity, so no parent-attribute pass is required.
     *
     * @return array<int, true>
     */
    private function defaultedCallIds(NodeFinder $finder, Node\Stmt\Class_ $class, string $name, string $kind): array
    {
        $ids = [];

        if ($kind === 'nullable') {
            foreach ($finder->findInstanceOf($class, Node\Expr\BinaryOp\Coalesce::class) as $coalesce) {
                if ($this->isThisCall($coalesce->left, $name) && $this->isConstant($coalesce->right)) {
                    $ids[spl_object_id($coalesce->left)] = true;
                }
            }

            return $ids;
        }

        // option: $this->m(...)->getOr(<const>)
        foreach ($finder->findInstanceOf($class, Node\Expr\MethodCall::class) as $call) {
            if ($call->name instanceof Node\Identifier
                && strtolower($call->name->toString()) === self::OPTION_GETOR
                && $this->isThisCall($call->var, $name)
                && ($call->args[0] ?? null) instanceof Node\Arg
                && $this->isConstant($call->args[0]->value)
            ) {
                $ids[spl_object_id($call->var)] = true;
            }
        }

        return $ids;
    }

    private function isThisCall(Node\Expr $expr, string $name): bool
    {
        return $expr instanceof Node\Expr\MethodCall
            && $expr->var instanceof Node\Expr\Variable
            && $expr->var->name === 'this'
            && $expr->name instanceof Node\Identifier
            && $expr->name->toString() === $name;
    }

    /** A fixed compile-time constant fallback — a scalar literal, a true/false, or a class constant (NOT null). */
    private function isConstant(Node\Expr $expr): bool
    {
        if ($expr instanceof Node\Scalar) {
            return true;
        }

        if ($expr instanceof Node\Expr\ClassConstFetch) {
            return true;
        }

        if ($expr instanceof Node\Expr\ConstFetch) {
            return strtolower($expr->name->toString()) !== 'null';
        }

        return false;
    }
}
