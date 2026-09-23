<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;
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
}
