<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * A binding pattern on the left of a declaration — a plain {@see NamePattern} (`const x`), an
 * {@see ObjectPattern} (`const { a, b: c }`), or an {@see ArrayPattern} (`const [a, b]`). Every
 * pattern reports the local {@see names} it binds, so `localNames()` sees each one.
 */
abstract class Pattern extends Node
{
    /**
     * The local names this pattern binds into scope.
     *
     * @return list<string>
     */
    abstract public function names(): array;
}
