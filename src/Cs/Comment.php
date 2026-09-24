<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

use DOMDocument;
use DOMElement;
use DOMText;
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
     * The `<summary>` and `<remarks>` elements of this doc comment, and any prose outside a tag, as written.
     *
     * @return list<string>
     */
    private function describingSections(): array
    {
        return array_values(array_map(static fn (DocTag $tag): string => $tag->xml, array_filter($this->tags(), static fn (DocTag $tag): bool => $tag->isDescription())));
    }

    /**
     * The top-level parts of this doc comment, in order — each tag, and each run of prose outside one; the
     * whole comment as one run of prose when it does not read as XML.
     *
     * @return list<DocTag>
     */
    public function tags(): array
    {
        $body = implode("\n", array_map(static fn (string $line): string => ltrim(trim($line), '/'), explode("\n", $this->text)));
        $document = new DOMDocument();

        if (! $document->loadXML("<doc>{$body}</doc>", LIBXML_NOERROR | LIBXML_NOWARNING)) {
            return [new DocTag(DocTag::PROSE, $body)];
        }

        $tags = [];

        foreach ($document->documentElement->childNodes ?? [] as $part) {
            if ($part instanceof DOMElement) {
                $tags[] = new DocTag($part->tagName, (string) $document->saveXML($part));
            }

            if ($part instanceof DOMText && trim($part->data) !== '') {
                $tags[] = new DocTag(DocTag::PROSE, $part->data);
            }
        }

        return $tags;
    }

    /**
     * Does this doc comment describe the signature and say nothing beyond $words — at least one tag for a
     * part of it, and every tag empty or made only of those words?
     *
     * @param  list<string>  $words
     */
    public function restatesOnly(array $words): bool
    {
        $tags = $this->tags();

        return array_any($tags, static fn (DocTag $tag): bool => ! $tag->isDescription() && $tag->isAboutTheSignature())
            && array_all($tags, static fn (DocTag $tag): bool => $tag->isAboutTheSignature() && $tag->saysNothingBeyond($words));
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
