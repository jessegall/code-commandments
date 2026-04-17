<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use PhpToken;

/**
 * Counts the lines of a method while ignoring lines that contain only comments.
 */
final class MethodLineCounter
{
    public static function count(string $content, int $startLine, int $endLine): int
    {
        $totalLines = $endLine - $startLine + 1;
        $commentOnlyLines = self::countCommentOnlyLines($content, $startLine, $endLine);

        return $totalLines - $commentOnlyLines;
    }

    private static function countCommentOnlyLines(string $content, int $startLine, int $endLine): int
    {
        $hasComment = [];
        $hasCode = [];

        foreach (PhpToken::tokenize($content) as $token) {
            if ($token->is(T_WHITESPACE)) {
                continue;
            }

            $startTokenLine = $token->line;
            $endTokenLine = $startTokenLine + substr_count($token->text, "\n");

            $bucket = $token->is([T_COMMENT, T_DOC_COMMENT]) ? 'comment' : 'code';

            for ($line = max($startTokenLine, $startLine); $line <= min($endTokenLine, $endLine); $line++) {
                if ($bucket === 'comment') {
                    $hasComment[$line] = true;
                } else {
                    $hasCode[$line] = true;
                }
            }
        }

        $count = 0;
        for ($line = $startLine; $line <= $endLine; $line++) {
            if (($hasComment[$line] ?? false) && ! ($hasCode[$line] ?? false)) {
                $count++;
            }
        }

        return $count;
    }
}
