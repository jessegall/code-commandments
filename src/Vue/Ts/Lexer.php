<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts;

use JesseGall\CodeCommandments\Vue\StringScan;

use JesseGall\CodeCommandments\Vue\Token;

/**
 * Tokenizes `<script setup>` source into lexemes for the Parser; emits punctuation one character
 * at a time and keeps byte spans.
 */
final class Lexer
{
    /**
     * @return list<Lexeme>
     */
    public function tokenize(string $source): array
    {
        $tokens = [];
        $length = strlen($source);
        $i = 0;

        while ($i < $length) {
            $char = $source[$i];

            if (ctype_space($char)) {
                $i++;
            } elseif ($char === '/' && ($source[$i + 1] ?? '') === '/') {
                $i = ($nl = strpos($source, "\n", $i)) === false ? $length : $nl;
            } elseif ($char === '/' && ($source[$i + 1] ?? '') === '*') {
                $i = ($end = strpos($source, '*/', $i)) === false ? $length : $end + 2;
            } elseif ($char === '"' || $char === "'" || $char === '`') {
                $start = $i;
                $i = StringScan::skip($source, $i, $char, $length);
                $tokens[] = new Lexeme(Token::STRING, substr($source, $start, $i - $start), $start, $i);
            } elseif (ctype_alpha($char) || $char === '_' || $char === '$') {
                $start = $i;
                while ($i < $length && (ctype_alnum($source[$i]) || $source[$i] === '_' || $source[$i] === '$')) {
                    $i++;
                }
                $tokens[] = new Lexeme(Token::IDENTIFIER, substr($source, $start, $i - $start), $start, $i);
            } elseif (ctype_digit($char)) {
                $start = $i;
                while ($i < $length && (ctype_alnum($source[$i]) || $source[$i] === '.' || $source[$i] === '_')) {
                    $i++;
                }
                $tokens[] = new Lexeme(Token::NUMBER, substr($source, $start, $i - $start), $start, $i);
            } else {
                $tokens[] = new Lexeme(Token::PUNCTUATION, $char, $i, $i + 1);
                $i++;
            }
        }

        return $tokens;
    }

    /**
     * Skip a string/template literal from its opening quote at $i, returning the index just past its
     * close. A backslash escapes the next character; an unterminated literal runs to end of source.
     */
}
