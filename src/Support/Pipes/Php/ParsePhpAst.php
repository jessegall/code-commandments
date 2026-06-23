<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\AstCache;
use JesseGall\CodeCommandments\Support\Pipes\Pipe;

/**
 * Parse PHP content into an AST.
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class ParsePhpAst implements Pipe
{
    public function handle(mixed $input): mixed
    {
        // Shared per-run memo: a file is parsed once regardless of how many
        // prophets (or parsing styles) touch it.
        return $input->with(ast: AstCache::parse($input->content));
    }
}
