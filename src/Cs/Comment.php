<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

use JesseGall\CodeCommandments\Support\Prose;

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
     * How many paragraphs of prose this comment holds — runs of words, a blank line or a tag on a line of its
     * own ending one, and each `<para>` a paragraph of its own. A doc comment counts only its `<summary>` and
     * `<remarks>`: the `<param>`, `<returns>` and `<exception>` tags are the member's contract, not its
     * description.
     */
    public function paragraphs(): int
    {
        $sections = $this->kind === CommentKind::Documentation ? $this->describingSections() : [implode("\n", $this->proseLines())];

        return array_sum(array_map(
            static fn (string $section): int => Prose::paragraphs(array_map(trim(...), explode("\n", strip_tags(str_replace(['<para>', '</para>'], "\n\n", $section)))), static fn (string $line): bool => $line !== ''),
            $sections,
        ));
    }

    /**
     * The `<summary>` and `<remarks>` elements of this doc comment, as written — the whole comment when it does
     * not read as XML.
     *
     * @return list<string>
     */
    private function describingSections(): array
    {
        $body = implode("\n", array_map(static fn (string $line): string => ltrim(trim($line), '/'), explode("\n", $this->text)));
        $document = simplexml_load_string("<doc>{$body}</doc>", options: LIBXML_NOERROR | LIBXML_NOWARNING);

        if ($document === false) {
            return [$body];
        }

        $elements = [...$document->xpath('summary') ?: [], ...$document->xpath('remarks') ?: []];

        return array_map(static fn (\SimpleXMLElement $element): string => (string) $element->asXML(), $elements);
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
