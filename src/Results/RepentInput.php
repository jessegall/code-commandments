<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Results;

/**
 * A single input a {@see \JesseGall\CodeCommandments\Contracts\ParameterizedRepenter}
 * needs from the user before it can apply a fix the tool cannot infer on its own
 * (e.g. the class name and cases of an enum to create).
 *
 * Supplied on the CLI as `--input <name>=<value>`.
 */
final class RepentInput
{
    public function __construct(
        public readonly string $name,
        public readonly bool $required,
        public readonly string $description,
        public readonly string $example = '',
    ) {}

    public static function required(string $name, string $description, string $example = ''): self
    {
        return new self($name, true, $description, $example);
    }

    public static function optional(string $name, string $description, string $example = ''): self
    {
        return new self($name, false, $description, $example);
    }
}
