<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

/**
 * Cuts Python source into {@see Token}s the way the language reads it. A logical line ends in NEWLINE;
 * a line indented deeper than the one before opens a block with INDENT, and a shallower one closes
 * blocks with DEDENT; inside brackets, or after a trailing backslash, a line runs on. Blank and
 * comment-only lines neither end nor open anything. Comments are kept beside the tokens
 * ({@see comments}), not among them. Total: anything it cannot read is stepped over, never thrown.
 */
final class Lexer
{
    /**
     * Every operator and delimiter, longest first, so `**=` is one token rather than `**` then `=`.
     */
    private const array OPERATORS = [
        '**=', '//=', '>>=', '<<=', '...',
        '->', ':=', '**', '//', '==', '!=', '<=', '>=', '<<', '>>',
        '+=', '-=', '*=', '/=', '%=', '&=', '|=', '^=', '@=',
        '+', '-', '*', '/', '%', '@', '&', '|', '^', '~', '<', '>',
        '(', ')', '[', ']', '{', '}', ',', ':', '.', ';', '=', '!',
    ];

    /**
     * The prefixes a string literal may carry, case aside — raw, bytes, unicode, formatted, and their pairs.
     */
    private const array STRING_PREFIXES = ['', 'r', 'u', 'b', 'f', 'br', 'rb', 'fr', 'rf'];

    /**
     * The column a tab advances indentation to a multiple of, as the interpreter counts it.
     */
    private const int TAB_WIDTH = 8;

    private string $source = '';

    private int $length = 0;

    private int $pos = 0;

    private int $depth = 0;

    private bool $atLineStart = true;

    /**
     * @var list<int>
     */
    private array $indents = [0];

    /**
     * @var list<Token>
     */
    private array $tokens = [];

    /**
     * @var list<Token>
     */
    private array $comments = [];

    /**
     * @return list<Token>
     */
    public function tokenize(string $source): array
    {
        $this->source = $source;
        $this->length = strlen($source);
        $this->pos = 0;
        $this->depth = 0;
        $this->atLineStart = true;
        $this->indents = [0];
        $this->tokens = [];
        $this->comments = [];

        while ($this->pos < $this->length) {
            if ($this->atLineStart && $this->depth === 0) {
                $this->takeIndentation();

                continue;
            }

            $this->step();
        }

        $this->finish();

        return $this->tokens;
    }

    /**
     * The comments of the source last tokenized, each with its span — where a `# @sin Name` marker is read.
     *
     * @return list<Token>
     */
    public function comments(): array
    {
        return $this->comments;
    }

    /**
     * Measure the indentation that opens a line and open or close blocks by it. A blank or
     * comment-only line is read past without touching the indentation at all.
     */
    private function takeIndentation(): void
    {
        $column = 0;

        while ($this->pos < $this->length && in_array($this->source[$this->pos], [' ', "\t", "\f"], true)) {
            $column = match ($this->source[$this->pos]) {
                "\t" => intdiv($column, self::TAB_WIDTH) * self::TAB_WIDTH + self::TAB_WIDTH,
                "\f" => 0,
                default => $column + 1,
            };
            $this->pos++;
        }

        if ($this->pos >= $this->length) {
            return;
        }

        $char = $this->source[$this->pos];

        if ($char === "\n" || $char === "\r") {
            $this->pos++;

            return;
        }

        if ($char === '#') {
            $this->takeComment();

            return;
        }

        $this->atLineStart = false;
        $this->indentTo($column);
    }

    /**
     * Open a block for a deeper column, close every block deeper than a shallower one. A column that
     * lands between two open levels (an inconsistent dedent) becomes the level itself, so the parse
     * goes on rather than stopping.
     */
    private function indentTo(int $column): void
    {
        if ($column > end($this->indents)) {
            $this->indents[] = $column;
            $this->tokens[] = new Token(TokenKind::Indent, '', $this->pos, $this->pos);

            return;
        }

        while ($column < end($this->indents)) {
            array_pop($this->indents);
            $this->tokens[] = new Token(TokenKind::Dedent, '', $this->pos, $this->pos);
        }

        if ($column > end($this->indents)) {
            $this->indents[] = $column;
        }
    }

    /**
     * Read ONE thing on a line already under way.
     */
    private function step(): void
    {
        $char = $this->source[$this->pos];

        match (true) {
            $char === "\n" => $this->takeNewline(),
            $char === ' ', $char === "\t", $char === "\f", $char === "\r" => $this->pos++,
            $char === '#' => $this->takeComment(),
            $char === '\\' => $this->takeContinuation(),
            $this->startsString($this->pos) => $this->emit(TokenKind::String, $this->stringEnd($this->pos)),
            self::startsName($char) => $this->emit(TokenKind::Name, $this->nameEnd($this->pos)),
            ctype_digit($char), $char === '.' && ctype_digit($this->charAt($this->pos + 1)) => $this->emit(TokenKind::Number, $this->numberEnd($this->pos)),
            default => $this->takeOperator(),
        };
    }

    /**
     * A newline ends the logical line — unless a bracket is still open, when the line runs on.
     */
    private function takeNewline(): void
    {
        if ($this->depth > 0) {
            $this->pos++;

            return;
        }

        $this->emit(TokenKind::Newline, $this->pos + 1);
        $this->atLineStart = true;
    }

    private function takeComment(): void
    {
        $end = strpos($this->source, "\n", $this->pos);
        $end = $end === false ? $this->length : $end;

        $this->comments[] = new Token(TokenKind::Comment, rtrim(substr($this->source, $this->pos, $end - $this->pos), "\r"), $this->pos, $end);
        $this->pos = $end;
    }

    /**
     * A backslash that ends a line joins the next one to it; anywhere else it is stepped over.
     */
    private function takeContinuation(): void
    {
        $next = $this->charAt($this->pos + 1);

        $this->pos += match (true) {
            $next === "\n" => 2,
            $next === "\r" && $this->charAt($this->pos + 2) === "\n" => 3,
            default => 1,
        };
    }

    private function takeOperator(): void
    {
        foreach (self::OPERATORS as $operator) {
            if (substr_compare($this->source, $operator, $this->pos, strlen($operator)) !== 0) {
                continue;
            }

            $this->depth = max(0, $this->depth + match ($operator) {
                '(', '[', '{' => 1,
                ')', ']', '}' => -1,
                default => 0,
            });
            $this->emit(TokenKind::Op, $this->pos + strlen($operator));

            return;
        }

        $this->pos++; // a character no Python token begins with — stepped over, so the scan always advances
    }

    /**
     * Emit `[pos, $end)` as a $kind token and move past it — the one place a token's text is cut.
     */
    private function emit(TokenKind $kind, int $end): void
    {
        $this->tokens[] = new Token($kind, substr($this->source, $this->pos, $end - $this->pos), $this->pos, $end);
        $this->pos = $end;
    }

    /**
     * Close the last line and every open block, then mark the end.
     */
    private function finish(): void
    {
        $last = end($this->tokens);

        if ($last !== false && ! in_array($last->kind, [TokenKind::Newline, TokenKind::Dedent], true)) {
            $this->tokens[] = new Token(TokenKind::Newline, '', $this->length, $this->length);
        }

        while (count($this->indents) > 1) {
            array_pop($this->indents);
            $this->tokens[] = new Token(TokenKind::Dedent, '', $this->length, $this->length);
        }

        $this->tokens[] = Token::end($this->length);
    }

    /**
     * Does a string literal begin at $at — a quote, or a valid prefix run straight into one?
     */
    private function startsString(int $at): bool
    {
        $quote = $this->nameEnd($at);

        return in_array($this->charAt($quote), ["'", '"'], true)
            && in_array(strtolower(substr($this->source, $at, $quote - $at)), self::STRING_PREFIXES, true);
    }

    /**
     * Where the string literal beginning at $at (prefix included) ends. An f-string's replacement
     * fields are read as code, so a quote inside one — even the string's own quote — does not end it.
     * An unterminated single-line string ends at its line.
     */
    private function stringEnd(int $at): int
    {
        $open = $this->nameEnd($at);
        $formatted = str_contains(strtolower(substr($this->source, $at, $open - $at)), 'f');
        $quote = $this->source[$open];
        $delimiter = substr($this->source, $open, 3) === str_repeat($quote, 3) ? str_repeat($quote, 3) : $quote;
        $i = $open + strlen($delimiter);

        while ($i < $this->length) {
            $char = $this->source[$i];

            if ($char === '\\') {
                $i += 2;

                continue;
            }

            if ($formatted && $char === '{') {
                $i = $this->charAt($i + 1) === '{' ? $i + 2 : $this->replacementFieldEnd($i + 1);

                continue;
            }

            if (substr_compare($this->source, $delimiter, $i, strlen($delimiter)) === 0) {
                return $i + strlen($delimiter);
            }

            if ($char === "\n" && strlen($delimiter) === 1) {
                return $i;
            }

            $i++;
        }

        return $this->length;
    }

    /**
     * Where the f-string replacement field whose `{` sits just before $at ends — past its matching `}`,
     * stepping over the strings (and nested f-strings) inside it.
     */
    private function replacementFieldEnd(int $at): int
    {
        $depth = 1;
        $i = $at;

        while ($i < $this->length && $depth > 0) {
            $char = $this->source[$i];

            if ($this->startsString($i)) {
                $i = $this->stringEnd($i);

                continue;
            }

            if (self::startsName($char)) {
                $i = $this->nameEnd($i);

                continue;
            }

            $depth += match ($char) {
                '{' => 1,
                '}' => -1,
                default => 0,
            };
            $i++;
        }

        return $i;
    }

    /**
     * Where the name beginning at $at ends ($at itself when no name begins there).
     */
    private function nameEnd(int $at): int
    {
        $i = $at;

        while ($i < $this->length && self::continuesName($this->source[$i])) {
            $i++;
        }

        return $i;
    }

    /**
     * Where the number beginning at $at ends: a based integer, or digits with a fraction, an exponent
     * and an imaginary `j` — separators and all.
     */
    private function numberEnd(int $at): int
    {
        if ($this->source[$at] === '0' && in_array(strtolower($this->charAt($at + 1)), ['x', 'o', 'b'], true)) {
            return $this->nameEnd($at + 2);
        }

        $i = $this->digitsEnd($at);

        if ($this->charAt($i) === '.') {
            $i = $this->digitsEnd($i + 1);
        }

        if (in_array($this->charAt($i), ['e', 'E'], true)) {
            $sign = in_array($this->charAt($i + 1), ['+', '-'], true) ? 1 : 0;

            if (ctype_digit($this->charAt($i + 1 + $sign))) {
                $i = $this->digitsEnd($i + 1 + $sign);
            }
        }

        return in_array($this->charAt($i), ['j', 'J'], true) ? $i + 1 : $i;
    }

    /**
     * The character at $at — none, the empty string, past the end of the source, where a lookahead
     * finds the text over rather than an error.
     */
    private function charAt(int $at): string
    {
        return $at < $this->length ? $this->source[$at] : '';
    }

    private function digitsEnd(int $at): int
    {
        $i = $at;

        while ($i < $this->length && (ctype_digit($this->source[$i]) || $this->source[$i] === '_')) {
            $i++;
        }

        return $i;
    }

    private static function startsName(string $char): bool
    {
        return ctype_alpha($char) || $char === '_' || ord($char) >= 0x80;
    }

    private static function continuesName(string $char): bool
    {
        return self::startsName($char) || ctype_digit($char);
    }
}
