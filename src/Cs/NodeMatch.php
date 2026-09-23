<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Span;

/**
 * A C# node a query found, with the file it is in — a statement, a member or an expression alike.
 * Non-final by design: subclass it to hang domain predicates a `where` closure can type-hint.
 */
class NodeMatch implements Located
{
    public function __construct(
        public readonly Node $node,
        public readonly ModuleFile $module,
    ) {}

    /**
     * The name the node declares or reads — a method's, a class's, an identifier's. Empty for anything
     * nameless.
     */
    public function name(): string
    {
        return $this->node->name ?? '';
    }

    public function line(): int
    {
        return $this->module->lineAt($this->node->start);
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
        return $this->module->spanAt($this->node->start, $this->node->end);
    }

    /**
     * What the node IS, named where it has a name — `MethodDeclaration Total`, `ClassDeclaration Order`.
     */
    public function scope(): string
    {
        return $this->name() === '' ? $this->node->kind : "{$this->node->kind} {$this->name()}";
    }
}
