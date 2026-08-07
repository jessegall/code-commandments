<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Doc;

/**
 * A document whose generated-block markers cannot be read as ONE block — two of them, a BEGIN with
 * no END, an END above its BEGIN. Splicing anyway means guessing which pair the human meant, and a
 * wrong guess deletes whatever stands between them. Raised so the caller decides: our own generated
 * documents should fail the build, a project's hand-written file should be left alone and reported.
 */
final class MalformedBlock extends \RuntimeException
{
    public static function of(string $name, string $problem): self
    {
        return new self("the `{$name}` block cannot be placed: {$problem}. Fix the markers by hand — writing over them would risk the text between.");
    }
}
