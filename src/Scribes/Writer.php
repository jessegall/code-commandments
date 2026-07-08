<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use PhpParser\Node;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Use_;

/**
 * The ONE code-writer every backend scribe composes — intent-level rewrites over a {@see Draft}, so no
 * scribe re-invents attribute stamping, import wiring, node replacement, or line deletion (and none of them
 * reaches for raw offset math: that lives in {@see Span}). A scribe describes WHAT to change
 * (`stampAttribute`, `ensureImport`, `replace`, `deleteStatementLine`); the Writer knows HOW. Built for
 * reuse — a new scribe needing one of these operations extends the Writer, never hand-rolls it.
 */
final class Writer
{
    private function __construct(
        private readonly Draft $draft,
        private readonly string $path,
        private readonly string $source,
        private readonly ?ClassLike $class,
    ) {}

    public static function for(Draft $draft, NodeMatch $match): self
    {
        return new self($draft, $match->file->path, $match->file->source, $match->enclosingClass());
    }

    /** Replace an AST node's whole span with $text. */
    public function replace(Node $node, string $text): void
    {
        $this->draft->edit(new Span($this->path, $this->source, $node->getStartFilePos(), $node->getEndFilePos() + 1), $text);
    }

    /** Insert $text at a byte offset (a zero-width edit). */
    public function insertAt(int $offset, string $text): void
    {
        $this->draft->edit(new Span($this->path, $this->source, $offset, $offset), $text);
    }

    /**
     * Stamp an attribute (`#[Computed]`, `#[TypeScript]`, `#[DataCollectionOf(X::class)]`, …) on its OWN line
     * directly above a property/param/class — beneath any existing attributes, aligned to the node's indent —
     * and ensure the attribute's `use` import. Composes {@see Span::skipWhitespace}/{@see Span::indentAt}; no
     * offset math here.
     */
    public function stampAttribute(Param|Property|ClassLike $node, string $attribute, ?string $importFqcn = null): void
    {
        $insertAt = $node->attrGroups === []
            ? $node->getStartFilePos()
            : end($node->attrGroups)->getEndFilePos() + 1;

        $keywordStart = Span::skipWhitespace($this->source, $insertAt);
        $lead = substr($this->source, $insertAt, $keywordStart - $insertAt);
        $indent = Span::indentAt($this->source, $node->getStartFilePos());

        $this->draft->edit(new Span($this->path, $this->source, $insertAt, $keywordStart), $lead . "{$attribute}\n{$indent}");

        if ($importFqcn !== null) {
            $this->ensureImport($importFqcn);
        }
    }

    /** Ensure `use $fqcn;` — added after the last existing `use`, else after the `namespace …;`. No-op when present or in the global namespace. */
    public function ensureImport(string $fqcn): void
    {
        $namespace = $this->class?->getAttribute('parent');

        if (! $namespace instanceof Namespace_) {
            return;
        }

        $uses = array_values(array_filter($namespace->stmts, static fn (Node $stmt): bool => $stmt instanceof Use_));

        foreach ($uses as $use) {
            foreach ($use->uses as $used) {
                if (ltrim($used->name->toString(), '\\') === $fqcn) {
                    return;
                }
            }
        }

        if ($uses !== []) {
            $this->insertAt(end($uses)->getEndFilePos() + 1, "\nuse {$fqcn};");

            return;
        }

        if ($namespace->name !== null && ($semicolon = Span::after($this->source, $namespace->name->getEndFilePos(), ';')) !== null) {
            $this->insertAt($semicolon + 1, "\n\nuse {$fqcn};");
        }
    }

    /** Drop a modifier keyword (`readonly`, `final`, …) from a typed property/param's modifier list. */
    public function dropModifier(Property|Param $node, string $modifier): void
    {
        if ($node->type === null) {
            return;
        }

        $typeStart = $node->type->getStartFilePos();
        $modifiersStart = $node->attrGroups === []
            ? $node->getStartFilePos()
            : end($node->attrGroups)->getEndFilePos() + 1;
        $keywordStart = Span::skipWhitespace($this->source, $modifiersStart, $typeStart);
        $modifiers = substr($this->source, $keywordStart, $typeStart - $keywordStart);

        $this->draft->edit(
            new Span($this->path, $this->source, $keywordStart, $typeStart),
            str_replace("{$modifier} ", '', $modifiers),
        );
    }

    /** A low-level replace of a byte range — for a scribe-specific edit with no node (e.g. a property's `;`). */
    public function rewriteRange(int $start, int $end, string $text): void
    {
        $this->draft->edit(new Span($this->path, $this->source, $start, $end), $text);
    }

    /** Delete the whole line(s) a statement occupies — its indentation through the trailing newline. */
    public function deleteStatementLine(Node $statement): void
    {
        $newlineBefore = Span::before($this->source, $statement->getStartFilePos(), "\n");
        $start = $newlineBefore === null ? 0 : $newlineBefore + 1;
        $end = $statement->getEndFilePos() + 1;

        if (($this->source[$end] ?? '') === "\n") {
            $end++;
        }

        $this->draft->edit(new Span($this->path, $this->source, $start, $end), '');
    }
}
