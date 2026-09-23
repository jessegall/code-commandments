<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\Node\ForLoop;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\Node\Node;
use JesseGall\CodeCommandments\Py\Node\Param;
use JesseGall\CodeCommandments\Span;

/**
 * A Python expression a query selected, together with its module — so it knows its `file:line`.
 * Non-final by design: subclass it to hang domain predicates a `where` closure can type-hint.
 */
class ExprMatch implements Located
{
    public function __construct(
        public readonly Expr $expr,
        public readonly ModuleFile $module,
    ) {}

    /**
     * What a call calls, as a dotted path — `os.listdir`, `self.rows().clear`. Empty for anything but a call.
     */
    public function callName(): string
    {
        return $this->expr->isCall() ? self::path($this->expr->get('callee')) : '';
    }

    public function line(): int
    {
        return $this->module->lineAt($this->expr->start);
    }

    public function file(): string
    {
        return $this->module->file;
    }

    public function location(): string
    {
        return $this->file() . ':' . $this->line();
    }

    public function span(): Span
    {
        return $this->module->spanAt($this->expr->start, $this->expr->end);
    }

    public function scope(): string
    {
        return $this->expr->kind->value;
    }

    /**
     * $expr as the dotted path it reads — a name, an attribute chain, a call written `()` along the way.
     */
    private static function path(Expr $expr): string
    {
        return match ($expr->kind) {
            ExprKind::Name => $expr->get('name'),
            ExprKind::Attribute => self::path($expr->get('object')) . '.' . $expr->get('name'),
            ExprKind::Call => self::path($expr->get('callee')) . '()',
            ExprKind::Subscript => self::path($expr->get('object')) . '[]',
            default => $expr->kind->value,
        };
    }

    /**
     * Is this expression passed straight into a call — a positional argument, or a keyword's value?
     * Named as the backend names it.
     */
    public function fillsArgument(): bool
    {
        $wrapper = $this->module->wrapperOf($this->expr);

        if ($wrapper->isSomeAnd(static fn (Expr $keyword): bool => $keyword->is(ExprKind::Keyword))) {
            $wrapper = $this->module->wrapperOf($wrapper->unwrap());
        }

        return $wrapper->isSomeAnd(fn (Expr $call): bool => $call->isCall() && $call->get('callee') !== $this->expr);
    }

    /**
     * Is this a string-key read — `row["sku"]`, `row.get("sku")` — of a name the enclosing function
     * annotates as a dict?
     */
    public function isDictKeyRead(): bool
    {
        $base = $this->expr->stringKeyBase()->filter(static fn (Expr $read): bool => $read->is(ExprKind::Name));

        return $base->isSomeAnd(fn (Expr $name): bool => $this->module->functionOf($this->expr)->isSomeAnd(
            static fn (FunctionDef $function): bool => $function->annotationOf((string) $name->get('name'))->isSomeAnd(
                static fn (Expr $annotation): bool => $annotation->isDictType(),
            ),
        ));
    }

    /**
     * Is this written in a named constructor — a `@classmethod` returning `cls(...)`?
     */
    public function isWithinNamedConstructor(): bool
    {
        return $this->module->functionOf($this->expr)->isSomeAnd(static fn (FunctionDef $function): bool => $function->isNamedConstructor());
    }

    /**
     * Is this the collection a `for` statement walks — `rows` in `for row in rows:`?
     */
    public function isLoopSubject(): bool
    {
        return $this->module->ownerOf($this->expr)->isSomeAnd(fn (Node $owner): bool => $owner instanceof ForLoop && $owner->iterable === $this->expr);
    }

    /**
     * Does this fall back to an empty collection — `x or []`, `m.get(k, ())`?
     */
    public function fallsBackToEmptyCollection(): bool
    {
        return $this->expr->fallback()->isSomeAnd(static fn (Expr $fallback): bool => $fallback->isEmptyCollection());
    }

    /**
     * Is what this falls back FROM read off a parameter the caller handed in — `below` in
     * `below.get(id, [])`? A method's own `self` or `cls` is not handed in: reading its state is the
     * object answering for itself.
     */
    public function fallbackReachesIntoParameter(): bool
    {
        return $this->expr->fallbackSubject()->isSomeAnd(fn (Expr $subject): bool => $this->module->functionOf($this->expr)->isSomeAnd(
            fn (FunctionDef $function): bool => in_array($subject->rootName(), $this->handedIn($function), true),
        ));
    }

    /**
     * Is this the outermost of a conditional expression nested in another's branch — the one finding a
     * chain of them yields?
     */
    public function isOutermostNestedConditional(): bool
    {
        return $this->expr->nestsConditional() && ! $this->isInsideConditional();
    }

    /**
     * Does any expression this one sits in — out to its statement — read as a conditional?
     */
    private function isInsideConditional(): bool
    {
        return array_any($this->module->wrappersOf($this->expr), static fn (Expr $around): bool => $around->is(ExprKind::Conditional));
    }

    /**
     * Is this the whole of a statement — a value computed and thrown away?
     */
    public function resultIsDiscarded(): bool
    {
        return $this->module->isDiscarded($this->expr);
    }

    /**
     * Is this defaulted value compared, `==` or `!=`, to its own fallback — `(x or '') != ''` — so that
     * absent and empty reach the same branch and nothing records which it was?
     */
    public function isComparedToItsFallback(): bool
    {
        return $this->expr->fallback()->isSomeAnd(fn (Expr $fallback): bool => $this->module->wrapperOf($this->expr)->isSomeAnd(
            fn (Expr $around): bool => $around->equatesWith($this->expr, $fallback),
        ));
    }

    /**
     * Is this one link of a larger `or` chain rather than the chain itself?
     */
    public function isInsideOr(): bool
    {
        return $this->module->wrapperOf($this->expr)->isSomeAnd(static fn (Expr $around): bool => $around->isOr());
    }

    /**
     * The names of the parameters a caller of $function supplies — every one but a bound method's first.
     *
     * @return list<string>
     */
    private function handedIn(FunctionDef $function): array
    {
        $names = array_map(static fn (Param $param): string => $param->name, $function->params);

        return $this->module->isMethod($function) && ! $function->isStatic() ? array_slice($names, 1) : $names;
    }
}
