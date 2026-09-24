<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

/**
 * A comment in a C# file — a `//` line, a delimited block, or a `///` documentation comment — with its text,
 * its span, and for a documentation comment, the `cref`s it names.
 */
final readonly class Comment
{
    /**
     * @param  list<Cref>  $crefs
     */
    public function __construct(
        public CommentKind $kind,
        public string $text,
        public int $start,
        public int $end,
        public array $crefs,
    ) {}

    /**
     * The words this comment says, as a reader reads them — its markers (`//`, `///`, the block delimiters, a
     * block's leading `*`) and a doc comment's XML tags taken away, one line per line.
     *
     * @return list<string>
     */
    public function proseLines(): array
    {
        $body = $this->kind === CommentKind::Block ? substr($this->text, 2, -2) : $this->text;
        $lines = preg_split('/\R/', strip_tags($body)) ?: [];

        return array_values(array_map(static fn (string $line): string => trim(ltrim(trim($line), '/*')), $lines));
    }

    /**
     * The words this comment says, on one line.
     */
    public function prose(): string
    {
        return trim(implode(' ', array_filter($this->proseLines(), static fn (string $line): bool => $line !== '')));
    }

    /**
     * @param  array<string, mixed>  $written  a comment as the bridge's contract writes it
     */
    public static function fromContract(array $written): self
    {
        return new self(
            CommentKind::from((string) $written['kind']),
            (string) $written['text'],
            (int) $written['start'],
            (int) $written['end'],
            array_map(Cref::fromContract(...), $written['crefs'] ?? []),
        );
    }
}
