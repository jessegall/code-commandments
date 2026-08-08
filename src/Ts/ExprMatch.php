<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ts;

use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Span;
use JesseGall\CodeCommandments\Ts\Expr\Expr;
use JesseGall\CodeCommandments\Ts\Node\ClassDecl;
use JesseGall\CodeCommandments\Ts\Node\FieldDecl;
use JesseGall\PhpTypes\Option;

/**
 * A matched {@see Expr} that knows WHERE it is. Expressions are their own tree, so they get their
 * own match — a rule about a `??` default or a `?.` chain points at the expression itself rather
 * than at the statement it happens to sit in.
 */
class ExprMatch implements Located
{
    public function __construct(
        public readonly Expr $expr,
        public readonly ModuleFile $module,
        public readonly ?ClassDecl $enclosingClass = null,
    ) {}

    /**
     * The field $name as the enclosing class declares it — none when this expression is not inside a
     * class, or the class declares no such field.
     *
     * @return Option<FieldDecl>
     */
    public function ownField(string $name): Option
    {
        $member = $this->enclosingClass?->member($name);

        return Option::fromNullable($member instanceof FieldDecl ? $member : null);
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

    /**
     * A short context for the report — the expression as it was written.
     */
    public function scope(): string
    {
        return $this->span()->text();
    }
}
