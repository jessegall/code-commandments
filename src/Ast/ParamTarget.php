<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use PhpParser\Node\Param;

/**
 * The PARAMETER an argument lands on, and the callee that declares it — what a forward walk needs
 * to keep following a value across a call boundary.
 */
final readonly class ParamTarget
{
    public function __construct(
        public Callee $callee,
        public Param $param,
    ) {}

    /**
     * The parameter's variable name, when it has a readable one — a destructured or variadic
     * parameter may not.
     */
    public function name(): ?string
    {
        return AstNode::variableNameOf($this->param->var) === null ? null : $this->param->var->name;
    }

    /**
     * Is this parameter also a PROMOTED property — so the value continues into the object's field?
     */
    public function isPromoted(): bool
    {
        return $this->param->flags !== 0;
    }

    /**
     * The slot this parameter IS, for the seen-set that stops a walk following it twice. None when
     * the parameter has no readable name to key on.
     */
    public function slot(): ?string
    {
        $name = $this->name();

        return $name === null ? null : "P:{$this->callee->class}::{$this->callee->method}#{$name}";
    }
}
