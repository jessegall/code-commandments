<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
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
}
