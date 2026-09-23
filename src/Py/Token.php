<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\Support\TokenOfKind;

/**
 * One token of Python source: its kind, its text and the byte span `[start, end)` it covers in the
 * file, so a node built from it can say where it is.
 */
final readonly class Token
{
    use TokenOfKind;

    public function __construct(
        public TokenKind $kind,
        public string $value,
        public int $start,
        public int $end,
    ) {}

    /**
     * What a parser reads past the last token: the end of the file, at $at.
     */
    public static function end(int $at): self
    {
        return new self(TokenKind::EndMarker, '', $at, $at);
    }


    /**
     * How this token moves a bracket depth: +1 for an opener, -1 for a closer, 0 for anything else.
     */
    public function groupDepthChange(): int
    {
        return match (true) {
            $this->kind !== TokenKind::Op => 0,
            in_array($this->value, ['(', '[', '{'], true) => 1,
            in_array($this->value, [')', ']', '}'], true) => -1,
            default => 0,
        };
    }

    public function isOp(string $value): bool
    {
        return $this->is(TokenKind::Op, $value);
    }

    public function isName(?string $value = null): bool
    {
        return $this->is(TokenKind::Name, $value);
    }
}
