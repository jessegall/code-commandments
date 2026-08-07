<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes;

use JesseGall\CodeCommandments\Span;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Param;
use PhpParser\Node\UseItem;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
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

    /**
     * Replace an AST node's whole span with $text.
     */
    public function replace(Node $node, string $text): void
    {
        $this->draft->edit(new Span($this->path, $this->source, $node->getStartFilePos(), $node->getEndFilePos() + 1), $text);
    }

    /**
     * The source text a node occupies, verbatim — the reusable "slice out what's written" a rewrite reuses.
     */
    public function textOf(Node $node): string
    {
        return self::slice($this->source, $node);
    }

    /**
     * A node's verbatim source cut from an explicit $source — the static form, for a scribe holding no Writer.
     */
    public static function slice(string $source, Node $node): string
    {
        return Span::slice($source, $node->getStartFilePos(), $node->getEndFilePos());
    }

    /**
     * Insert $text at a byte offset (a zero-width edit).
     */
    public function insertAt(int $offset, string $text): void
    {
        $this->draft->edit(new Span($this->path, $this->source, $offset, $offset), $text);
    }

    /**
     * Stamp an attribute (`#[Computed]`, `#[TypeScript]`, `#[DataCollectionOf(X::class)]`, …) on its OWN line
     * directly above a property/param/class — beneath any existing attributes, aligned to the node's indent —
     * and ensure the attribute's `use` import. All offset math goes through {@see Span::skipWhitespace} and
     * {@see Span::indentAt}.
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

    /**
     * Ensure `use $fqcn;` — added after the last existing `use`, else after the `namespace …;`. No-op when present or in the global namespace.
     */
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

    /**
     * Drop `use $fqcn;` — the inverse of {@see ensureImport}, for a fix that leaves an import with
     * nothing to name. Only the one clause goes: a grouped `use A, B;` keeps its siblings, so a name
     * the file still spells keeps its import. No-op when the import isn't there.
     */
    public function dropImport(string $fqcn): void
    {
        $namespace = $this->class?->getAttribute('parent');

        if (! $namespace instanceof Namespace_) {
            return;
        }

        foreach ($namespace->stmts as $stmt) {
            if (! $stmt instanceof Use_) {
                continue;
            }

            foreach ($stmt->uses as $used) {
                if (ltrim($used->name->toString(), '\\') !== $fqcn) {
                    continue;
                }

                if (count($stmt->uses) === 1) {
                    $this->deleteStatementLine($stmt);
                } else {
                    $this->dropClause($stmt, $used);
                }

                return;
            }
        }
    }

    /**
     * Remove ONE clause from a grouped `use A, B, C;` — the clause plus the comma that joins it to
     * its neighbour, taken from whichever side keeps the remaining list well-formed.
     */
    private function dropClause(Use_ $use, UseItem $clause): void
    {
        $index = array_search($clause, $use->uses, true);
        $previous = $index > 0 ? $use->uses[$index - 1] : null;

        $start = $previous === null ? $clause->getStartFilePos() : $previous->getEndFilePos() + 1;
        $end = $previous === null
            ? (Span::after($this->source, $clause->getEndFilePos(), ',') ?? $clause->getEndFilePos()) + 1
            : $clause->getEndFilePos() + 1;

        $this->draft->edit(new Span($this->path, $this->source, $start, Span::skipWhitespace($this->source, $end)), '');
    }

    /**
     * Drop a modifier keyword (`readonly`, `final`, …) from a typed property/param's modifier list.
     */
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

    /**
     * Drop a declared return type — the `: Type` between the parameter list and the body, taken out
     * whole so nothing is left dangling. No-op when the function declares none.
     */
    public function removeReturnType(ArrowFunction|ClassMethod|Closure|Function_ $function): void
    {
        if ($function->returnType === null) {
            return;
        }

        $start = $function->returnType->getStartFilePos();
        $colon = Span::before($this->source, $start, ':');

        $this->rewrite(new Edit($colon ?? $start, $function->returnType->getEndFilePos() + 1, ''));
    }

    /**
     * Replace a declaration's docblock with $text — the doc comment's own byte range, so the
     * declaration under it is untouched and nothing has to hunt for where the comment ends.
     */
    public function replaceDocblock(Node $node, string $text): void
    {
        $doc = $node->getDocComment();

        if ($doc === null) {
            return;
        }

        $this->rewrite(Edit::overNode($doc, $text));
    }

    /**
     * Replace a run of comments — the first through the last — with $text. How a stack of docblocks
     * becomes one: the whole run goes, the merged block takes its place, and the declaration beneath
     * is untouched.
     *
     * @param  list<\PhpParser\Comment>  $comments  in source order
     */
    public function replaceComments(array $comments, string $text): void
    {
        if ($comments === []) {
            return;
        }

        $last = $comments[count($comments) - 1];

        $this->rewrite(new Edit($comments[0]->getStartFilePos(), $last->getEndFilePos() + 1, $text));
    }

    /**
     * A low-level replace — for a scribe-specific edit with no node (e.g. a property's `;`). The
     * range and its text travel together as one {@see Edit}, which is also what a {@see Draft}
     * holds, so nothing re-states the trio.
     */
    public function rewrite(Edit $edit): void
    {
        $this->draft->edit(new Span($this->path, $this->source, $edit->start, $edit->end), $edit->text);
    }

    /**
     * Relocate declarations, in the order given, to just above $anchor — each carried with its
     * docblock, its attributes and its own line, and separated by a blank line. One insertion and one
     * deletion per node, so the moved text is the original bytes: no reprinting, no reformatting.
     *
     * @param  list<Node>  $nodes  each must sit AFTER $anchor in the same file
     */
    public function moveBefore(array $nodes, Node $anchor): void
    {
        $blocks = [];

        foreach ($nodes as $node) {
            [$start, $end] = $this->declarationBounds($node);
            $blocks[] = trim(Span::slice($this->source, $start, $end - 1), "\n");
            $this->draft->edit(new Span($this->path, $this->source, $start, $end), '');
        }

        if ($blocks !== []) {
            // The anchor's own line — NOT its blank-line-swallowing bounds, or the block would land
            // above the blank that already separates the anchor from the head of the class.
            $this->insertAt($this->lineStartOf($anchor), implode("\n\n", $blocks) . "\n\n");
        }
    }

    /**
     * Rewrite a run of declarations into $groups — the same nodes, regrouped. One edit over the whole
     * run, so no two moves can overlap, and each declaration is re-emitted as its ORIGINAL bytes
     * (docblock, attributes, alignment). A blank line separates the groups; INSIDE a group each member
     * keeps the spacing it had, so a tight block stays tight and a spaced one stays spaced.
     *
     * @param  list<Node>         $nodes   the run as it stands, in source order
     * @param  list<list<Node>>   $groups  the same nodes, grouped and in the order they should read
     */
    public function reorder(array $nodes, array $groups): void
    {
        if ($nodes === []) {
            return;
        }

        $pieces = [];

        foreach ($groups as $group) {
            foreach ($group as $index => $node) {
                $start = $this->lineStartOf($node);
                $opensGroup = $index === 0;
                $blank = $pieces !== [] && ($opensGroup || $this->hasBlankLineAbove($start));

                $pieces[] = ($blank ? "\n" : '') . Span::slice($this->source, $start, $node->getEndFilePos());
            }
        }

        $first = $this->lineStartOf($nodes[0]);
        $last = $nodes[count($nodes) - 1]->getEndFilePos() + 1;

        $this->draft->edit(new Span($this->path, $this->source, $first, $last), implode("\n", $pieces));
    }

    private function hasBlankLineAbove(int $lineStart): bool
    {
        return $lineStart >= 2 && $this->source[$lineStart - 1] === "\n" && $this->source[$lineStart - 2] === "\n";
    }

    /**
     * Where a declaration really begins and ends in the source: from the start of the line its
     * docblock (or its attributes, or the declaration itself) opens on, through the newline that ends
     * it. The BLANK line above is taken too, so lifting the block out closes the gap behind it instead
     * of leaving one hanging before the next member (or the closing brace).
     *
     * @return array{0: int, 1: int}  [start, end-exclusive]
     */
    private function declarationBounds(Node $node): array
    {
        $start = $this->lineStartOf($node);

        while ($start >= 2 && $this->source[$start - 1] === "\n" && $this->source[$start - 2] === "\n") {
            $start--;
        }

        $end = $node->getEndFilePos() + 1;

        return [$start, ($this->source[$end] ?? '') === "\n" ? $end + 1 : $end];
    }

    /**
     * The offset the declaration's own line begins at — its docblock or attributes included, its
     * indentation kept, and nothing of the line before it.
     */
    private function lineStartOf(Node $node): int
    {
        $start = $node->getStartFilePos();

        foreach ($node->getComments() as $comment) {
            $start = min($start, $comment->getStartFilePos());
        }

        $newlineBefore = Span::before($this->source, $start, "\n");

        return $newlineBefore === null ? 0 : $newlineBefore + 1;
    }

    /**
     * Delete the whole line(s) a statement occupies — its indentation through the trailing newline.
     */
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
