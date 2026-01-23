<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\Pipe;
use PhpParser\Error;
use PhpParser\ParserFactory;

/**
 * Parse PHP content into an AST.
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class ParsePhpAst implements Pipe
{
    public function handle(mixed $input): mixed
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse($input->content);
        } catch (Error) {
            $ast = null;
        }

        return $input->with(ast: $ast);
    }
}
