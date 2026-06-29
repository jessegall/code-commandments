<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

/**
 * A matched {@see Element} that knows WHERE it is — the backend's NodeMatch, for
 * Vue. It IS an Element (so a `where`/`reject` predicate reads the same), plus the
 * source component and a `file:line` location a finding can point at.
 *
 * Tree navigation delegates to the original node ({@see $node}): a match is a copy,
 * so searching for "itself" among its parent's children must use the real object.
 */
final class ElementMatch extends Element
{
    public function __construct(public readonly Element $node, public readonly Sfc $sfc)
    {
        parent::__construct($node->tag, $node->attributes, $node->children, $node->line, $node->text, $node->start, $node->end);

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
