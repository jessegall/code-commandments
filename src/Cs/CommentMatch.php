<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

use JesseGall\CodeCommandments\Located;

/**
 * A comment a rule found, with the file it is in.
 */
final readonly class CommentMatch implements Located
{
    public function __construct(
        public Comment $comment,
        public ModuleFile $module,
    ) {}

    public function file(): string
    {
        return $this->module->file;
    }

    /**
     * The line of the code this comment is about — the first declaration or statement after it, where a reader
     * meets it — or the comment's own line when nothing follows.
     */
    public function line(): int
    {
        $after = array_filter($this->module->nodes(), fn (Node $node): bool => $node->start >= $this->comment->end && $node->role !== 'other');
        $next = array_reduce($after, static fn (?Node $first, Node $node): Node => $first === null || $node->start < $first->start ? $node : $first);

        return $this->module->lineAt($next->start ?? $this->comment->start);
    }

    public function location(): string
    {
        return $this->file() . ':' . $this->line();
    }

    public function scope(): string
    {
        return $this->comment->kind->name . ' comment';
    }
}
