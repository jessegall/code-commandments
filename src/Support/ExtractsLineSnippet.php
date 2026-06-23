<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\PhpTypes\T_String;

/**
 * The trimmed source line at a 1-based line number — the shared snippet
 * extraction the AST prophets and pipes need for their match output.
 */
trait ExtractsLineSnippet
{
    protected function lineSnippet(string $content, int $line): string
    {
        $lines = explode(T_String::NEWLINE, $content);

        return isset($lines[$line - 1]) ? trim($lines[$line - 1]) : T_String::empty();
    }
}
