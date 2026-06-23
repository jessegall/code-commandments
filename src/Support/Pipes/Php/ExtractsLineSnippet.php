<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\PhpTypes\T_String;

/**
 * The trimmed source line at a 1-based line number — the shared snippet
 * extraction every AST pipe needs for its match output.
 */
trait ExtractsLineSnippet
{
    private function lineSnippet(string $content, int $line): string
    {
        $lines = explode(T_String::NEWLINE, $content);

        return isset($lines[$line - 1]) ? trim($lines[$line - 1]) : T_String::empty();
    }
}
