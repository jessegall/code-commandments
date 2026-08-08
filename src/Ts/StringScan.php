<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ts;

/**
 * Char-level string-literal scanning shared by the two lexing layers — the SFC {@see Script} scanner and
 * the {@see \JesseGall\CodeCommandments\Ts\Lexer}. Genuine tokenizing (not structural parsing), so raw
 * index walking is right here; keeping it in ONE place means both lexers skip a quoted string, escapes and
 * all, exactly the same way.
 */
final class StringScan
{
    /**
     * Given $source and the offset $i of an OPENING $quote, the offset just past the matching closing quote
     * (honouring `\` escapes), or $length if the string runs to the end unterminated.
     */
    public static function skip(string $source, int $i, string $quote, int $length): int
    {
        for ($i++; $i < $length; $i++) {
            if ($source[$i] === '\\') {
                $i++;
            } elseif ($source[$i] === $quote) {
                return $i + 1;
            }
        }

        return $length;
    }
}
