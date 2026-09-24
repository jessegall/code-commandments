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
