<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

use InvalidArgumentException;

/**
 * A command line or a served request the bridge cannot answer, saying what is wrong with it.
 */
final class BadRequest extends InvalidArgumentException
{
    public static function forFlag(string $flag): self
    {
        return new self("unknown flag {$flag}");
    }

    public static function forNothingToRead(): self
    {
        return new self('usage: bridge.php [--write=PATH]... [--autoload=FILE] [--rename=FROM=TO]... [--serve] PATH...');
    }

    public static function forNoPaths(): self
    {
        return new self('a request names no paths');
    }

    public static function forMissingPath(string $path): self
    {
        return new self("no such path {$path}");
    }

    public static function forRename(string $pair): self
    {
        return new self("--rename takes FROM=TO, not {$pair}");
    }
}
