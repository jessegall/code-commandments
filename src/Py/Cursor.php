<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

/**
 * A position in a Python token stream, shared by the statement and the expression parser so each
 * reads on from where the other stopped. Looking past the last token yields the end marker, never an
 * error. $base is where the tokenized source begins in its file, so every offset it reports is a
 * position in the file.
 */
final class Cursor
{
    private int $pos = 0;

    private readonly Token $end;

    /**
     * @param  list<Token>  $tokens
     */
    public function __construct(private readonly array $tokens, private readonly int $base = 0)
    {
        $last = end($tokens);
        $this->end = Token::end($last === false ? 0 : $last->end);
    }

    public function peek(): Token
    {
        return $this->at(0);
    }

    public function at(int $ahead): Token
    {
        return $this->tokens[$this->pos + $ahead] ?? $this->end;
    }

    /**
     * Step past the token under the cursor and return it. At the end of the stream the cursor stays.
     */
    public function advance(): Token
    {
        $token = $this->peek();

        if ($this->pos < count($this->tokens)) {
            $this->pos++;
        }

        return $token;
    }

    public function atOp(string $value): bool
    {
        return $this->peek()->isOp($value);
    }

    public function atName(string $value): bool
    {
        return $this->peek()->isName($value);
    }

    public function atEnd(): bool
    {
        return $this->peek()->is(TokenKind::EndMarker);
    }

    public function advanceIfOp(string $value): bool
    {
        if (! $this->atOp($value)) {
            return false;
        }

        $this->advance();

        return true;
    }

    public function advanceIfName(string $value): bool
    {
        if (! $this->atName($value)) {
            return false;
        }

        $this->advance();

        return true;
    }

    /**
     * Does a `.name` follow — an attribute, a dotted module path — rather than a `.` cut off?
     */
    public function atDottedName(): bool
    {
        return $this->atOp('.') && $this->at(1)->isName();
    }

    /**
     * Is the cursor still inside a group that $closer ends — before it, and not past the last token?
     */
    public function isBefore(string $closer): bool
    {
        return ! $this->atOp($closer) && ! $this->atEnd();
    }

    /**
     * Step past the bracket group that opens under the cursor, nested groups and all.
     */
    public function skipGroup(): void
    {
        $depth = 0;

        while (! $this->atEnd()) {
            $depth += $this->advance()->groupDepthChange();

            if ($depth <= 0) {
                return;
            }
        }
    }

    /**
     * Where the token under the cursor begins, in the file.
     */
    public function offset(): int
    {
        return $this->base + $this->peek()->start;
    }

    /**
     * Where the last code token stepped past ends, in the file — a block closes on the DEDENT that stands
     * at the next line, and what it spans ends with its last statement, not there.
     */
    public function consumedEnd(): int
    {
        $last = $this->pos - 1;

        while ($last > 0 && $this->tokens[$last]->kind->isLayout()) {
            $last--;
        }

        return $this->base + ($last >= 0 ? $this->tokens[$last]->end : 0);
    }

    /**
     * The cursor's place, to come back to after reading ahead.
     */
    public function mark(): int
    {
        return $this->pos;
    }

    public function rewind(int $mark): void
    {
        $this->pos = $mark;
    }
}
