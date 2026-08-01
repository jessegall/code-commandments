<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\State;

/**
 * A state value was written or read under a name its {@see Legend} does not declare — a typo, or a
 * value someone added to a file without saying what it means. Both are bugs, and both used to be
 * silent: the write landed in the file, every reader went on answering with its default, and the
 * state simply never moved.
 *
 * The legend is the SCHEMA, not just the prose: a name that isn't in it does not exist.
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
