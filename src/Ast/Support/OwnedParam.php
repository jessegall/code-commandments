<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

/**
 * The one parameter of a method that names an object THIS codebase declares — the candidate owner
 * a lookup-envy method should move onto ({@see LookupEnvy}): the variable it is read through and
 * the class it is typed as.
 */
final readonly class OwnedParam
{
    public function __construct(public string $name, public string $type) {}
}
