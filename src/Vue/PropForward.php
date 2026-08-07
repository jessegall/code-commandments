<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

/**
 * One site where a component hands a prop straight on to a child — the call site (`<Child :label>`)
 * and the name the CHILD knows it by, which is what the next hop is looked up under. The two always
 * travel together: a forward is only followable if you have both.
 */
final readonly class PropForward
{
    public function __construct(
        public ElementMatch $element,
        public string $childProp,
    ) {}
}
