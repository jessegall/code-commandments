<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

/**
 * One stop on a variable's journey: the occurrence of the variable, and what is
 * done to it there. The element type of {@see AstNode::trace()}.
 */
final class Interaction
{
    public function __construct(
        public readonly NodeMatch $node,
        public readonly InteractionKind $kind,
    ) {}

    /**
     * Where this interaction happens, as `path:line`.
     */
    public function location(): string
    {
        return $this->node->location();
    }

    /**
     * Is the variable written here (`$x = …`)?
     */
    public function isWrite(): bool
    {
        return $this->kind === InteractionKind::Assigned;
    }

    /**
     * Is the variable null-guarded here (`=== null`, `??`, `?->`)?
     */
    public function deNulls(): bool
    {
        return $this->kind->deNulls();
    }
}
