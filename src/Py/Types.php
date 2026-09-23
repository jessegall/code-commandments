<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\PhpTypes\Option;

/**
 * The types mypy resolved in a codebase, by file and span — empty where no bridge ran.
 */
final class Types
{
    /**
     * @var array<string, string>  each file as a module names it, to the path the bridge names it by
     */
    private array $resolved = [];

    /**
     * @param  array<string, array<string, Type>>  $byFile  resolved path => "start:end" => its type
     */
    public function __construct(private readonly array $byFile = []) {}

    /**
     * The type of the expression spanning `[$start, $end)` in $file — none where mypy resolved nothing.
     *
     * @return Option<Type>
     */
    public function at(string $file, int $start, int $end): Option
    {
        $this->resolved[$file] ??= realpath($file) ?: $file;

        return Option::fromNullable($this->byFile[$this->resolved[$file]]["{$start}:{$end}"] ?? null);
    }
}
