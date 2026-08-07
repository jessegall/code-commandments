<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use RuntimeException;

/**
 * The source of a file this codebase never parsed. Asking for it is a programming error, not a
 * value: a scribe splices against the bytes its node offsets index, so an absent source would be
 * spliced against nothing and write out whatever that produced.
 */
final class UnknownFile extends RuntimeException
{
    public static function at(string $path): self
    {
        return new self("This codebase holds no source for {$path} — it was never scanned, or the path is not the one it was parsed under.");
    }
}
