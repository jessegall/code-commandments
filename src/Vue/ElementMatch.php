<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Scribes\Span;

/**
 * A matched {@see Element} that knows WHERE it is — the backend's NodeMatch, for
 * Vue. It IS an Element (so a `where`/`reject` predicate reads the same), plus the
 * source component and a `file:line` location a finding can point at.
 *
 * Tree navigation delegates to the original node ({@see $node}): a match is a copy,
 * so searching for "itself" among its parent's children must use the real object.
 *
 * NOT final on purpose — the frontend mirror of NodeMatch: a project SUBCLASSES it to hang
 * domain predicates on the element, and a `where` closure that type-hints the subclass is handed
 * it ({@see \JesseGall\CodeCommandments\Query::where}).
 */
class ElementMatch extends Element implements Located
{
    public function __construct(public readonly Element $node, public readonly Sfc $sfc)
    {
        parent::__construct($node->tag, $node->attributes, $node->children, $node->line, $node->text, $node->start, $node->end, $node->attributeSpans);

        $this->parent = $node->parent;
    }

    public function followingElements(): array
    {
        return $this->node->followingElements();
    }

    public function file(): string
    {
        return $this->sfc->path;
    }

    /**
     * The path of a file sitting beside this match's own — where an extracted sibling
     * component is written (`dirname(this) / $filename`).
     */
    public function sibling(string $filename): string
    {
        return dirname($this->file()) . '/' . $filename;
    }

    /**
     * Where this match sits — its file, that file's source, and the byte range it
     * occupies — so a {@see \JesseGall\CodeCommandments\Scribes\RepentScribe}
     * rewrites it the same way a backend match is rewritten.
     */
    public function span(): Span
    {
        return new Span($this->sfc->path, $this->sfc->source, $this->start, $this->end);
    }

    public function location(): string
    {
        return $this->sfc->path . ':' . $this->line;
    }

    /**
     * A short context for the report — the element the sin sits on.
     */
    public function scope(): string
    {
        return "<{$this->tag}>";
    }
}
