<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Expr;

use JesseGall\CodeCommandments\Py\Lexer;
use JesseGall\CodeCommandments\Py\Token;
use JesseGall\CodeCommandments\Py\TokenKind;

/**
 * Reads one f-string literal into its parts, in order: the text between fields as string literals, each
 * replacement field as the expression it holds, and a field's conversion and format spec (`!r:>10`) as
 * a format literal — with a field nested in the spec (`{value:>{width}}`) read as an expression too.
 * `{{` and `}}` are text. A field's expression is found by the Python lexer, so a bracket, a nested
 * string or a `!=` inside it never ends it early.
 */
final class FStringReader
{
    private int $pos = 0;

    /**
     * @var list<Expr>
     */
    private array $parts = [];

    private function __construct(
        private readonly string $content,
        private readonly int $base,
    ) {}

    /**
     * The parts of the f-string $literal (prefix and quotes included) that begins at $base in its file.
     *
     * @return list<Expr>
     */
    public static function parts(string $literal, int $base): array
    {
        $prefix = strcspn($literal, '\'"');
        $quote = substr($literal, $prefix, 3) === str_repeat($literal[$prefix], 3) ? 3 : 1;
        $open = $prefix + $quote;
        $close = strlen($literal) - (strlen($literal) - $open >= $quote ? $quote : 0);
        $reader = new self(substr($literal, $open, max(0, $close - $open)), $base + $open);
        $reader->text('');

        return $reader->parts;
    }

    /**
     * Read text and fields until the content ends — or, inside a format spec, until the `}` closing the
     * field it belongs to. $lead is text already read ahead of the cursor (a conversion and its `:`).
     */
    private function text(string $lead, bool $inSpec = false): void
    {
        $text = $lead;
        $start = $this->pos - strlen($lead);

        while ($this->pos < strlen($this->content)) {
            $char = $this->content[$this->pos];
            $pair = substr($this->content, $this->pos, 2);

            if (! $inSpec && ($pair === '{{' || $pair === '}}')) {
                $text .= $char;
                $this->pos += 2;

                continue;
            }

            if ($char === '{') {
                $this->literal($text, $start, $inSpec);
                $this->pos++;
                $this->field();
                $text = '';
                $start = $this->pos;

                continue;
            }

            if ($char === '}' && $inSpec) {
                $this->literal($text, $start, $inSpec);
                $this->pos++;

                return;
            }

            $text .= $char;
            $this->pos++;
        }

        $this->literal($text, $start, $inSpec);
    }

    /**
     * One replacement field, its `{` already read: the expression, then a conversion and a spec when
     * written, through the closing `}`.
     */
    private function field(): void
    {
        $length = $this->expressionLength();
        $this->parts[] = Parser::parse(substr($this->content, $this->pos, $length), $this->base + $this->pos);
        $this->pos += $length;
        $conversion = '';

        if ($this->charAt($this->pos) === '!') {
            $conversion = '!' . substr($this->content, $this->pos + 1, strspn($this->content, 'rsa', $this->pos + 1));
            $this->pos += strlen($conversion);
        }

        if ($this->charAt($this->pos) === ':') {
            $this->pos++;
            $this->text($conversion . ':', inSpec: true);

            return;
        }

        $this->literal($conversion, $this->pos - strlen($conversion), inSpec: true);
        $this->pos++; // the closing `}`
    }

    /**
     * The character at $at — none, the empty string, past the end of the content.
     */
    private function charAt(int $at): string
    {
        return $at < strlen($this->content) ? $this->content[$at] : '';
    }

    /**
     * How long the field's expression is — up to the first `}`, `!` or `:` outside any bracket.
     */
    private function expressionLength(): int
    {
        $rest = substr($this->content, $this->pos);
        $depth = 0;

        foreach (new Lexer()->tokenize($rest) as $token) {
            if ($depth === 0 && self::endsExpression($token)) {
                return $token->start;
            }

            $depth += $token->groupDepthChange();
        }

        return strlen($rest);
    }

    private static function endsExpression(Token $token): bool
    {
        return $token->kind === TokenKind::Op && in_array($token->value, ['}', '!', ':'], true);
    }

    /**
     * $text as a part, unless there is none: a string literal between fields, a format literal inside one.
     */
    private function literal(string $text, int $start, bool $inSpec): void
    {
        if ($text === '') {
            return;
        }

        $this->parts[] = new Expr(ExprKind::Literal, ['type' => $inSpec ? 'format' : 'string', 'value' => $text])
            ->locatedAt($this->base + $start, $this->base + $this->pos);
    }
}
