<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use JesseGall\PhpTypes\Option;

/**
 * The class and method a call NAMES — `Money::from(…)` is `Money` and `from`. The pair four
 * analyses were each taking apart by hand, each with its own idea of whether to strip the leading
 * `\` and what `self` means.
 */
final readonly class Callee
{
    public function __construct(
        public string $class,
        public string $method,
    ) {}

    /**
     * The callee a STATIC call names — none when either side is not statically known (a dynamic
     * `$class::$method()`). The class comes back unqualified of its leading `\`; a RELATIVE name
     * (`self`, `static`, `parent`) is left exactly as written, because what it resolves to depends
     * on whose question it is — {@see resolvedAgainst} is where a caller says.
     *
     * @return Option<self>
     */
    public static function ofStaticCall(?Node $call): Option
    {
        if (! $call instanceof StaticCall || ! $call->class instanceof Name || ! $call->name instanceof Identifier) {
            return Option::none();
        }

        return Option::some(new self(ltrim($call->class->toString(), '\\'), $call->name->toString()));
    }

    /**
     * Is the class a RELATIVE name — one that only means something from inside a class body?
     */
    public function isRelative(): bool
    {
        return in_array($this->class, ['self', 'static', 'parent'], true);
    }

    /**
     * The same callee with a relative class resolved to $enclosing — the class the call was
     * written in. Unchanged when the name is already absolute, or when there is no enclosing class
     * to resolve against.
     */
    public function resolvedAgainst(?string $enclosing): self
    {
        return $this->isRelative() && $enclosing !== null
            ? new self(ltrim($enclosing, '\\'), $this->method)
            : $this;
    }
}
