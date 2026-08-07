<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use JesseGall\PhpTypes\Option;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Param;
use PhpParser\Node\Expr\Variable;

/**
 * A function's declared parameters, asked about the two ways an argument reaches one: BY NAME (a
 * named argument, and "what is `$x` declared as here?") and BY POSITION. One home for the walk both
 * questions were each spelling — a `foreach` over the params comparing `$param->var->name`.
 */
final readonly class ParamList
{
    /**
     * @param  list<Param>  $params
     */
    public function __construct(public array $params) {}

    /**
     * The parameters of $function — empty when there is no function, so a caller with a missing
     * declaration asks the same questions and simply gets none back.
     */
    public static function of(?FunctionLike $function): self
    {
        return new self(array_values($function?->getParams() ?? []));
    }

    /**
     * The parameter written as `$name`. None when the function declares no such parameter.
     *
     * @return Option<Param>
     */
    public function named(string $name): Option
    {
        foreach ($this->params as $param) {
            if ($param->var instanceof Variable && $param->var->name === $name) {
                return Option::some($param);
            }
        }

        return Option::none();
    }

    /**
     * The parameter in slot $position — where a POSITIONAL argument lands. None past the last
     * declared one, which is where a variadic or an over-supplied call ends up.
     *
     * @return Option<Param>
     */
    public function at(int $position): Option
    {
        return Option::fromNullable($this->params[$position] ?? null);
    }

    public function isEmpty(): bool
    {
        return $this->params === [];
    }
}
