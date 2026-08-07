<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\State;

/**
 * A state value whose name is missing from its {@see Legend} — a typo, or a value added to a file
 * without saying what it means. The legend is the schema, so the message lists the names it declares.
 */
final class UnknownValue extends \InvalidArgumentException
{
    /**
     * @param  list<string>  $declared
     */
    public static function for(string $name, array $declared, string $path): self
    {
        return new self(
            "No state value named '{$name}' in {$path} — declare it in the file's Legend (with what it "
            . 'means) before reading or writing it. Declared: ' . implode(', ', $declared) . '.',
        );
    }
}
