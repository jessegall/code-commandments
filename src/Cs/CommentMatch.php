<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

use JesseGall\CodeCommandments\Located;
use JesseGall\PhpTypes\Option;

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
     * The line of the code this comment is about, where a reader meets it — or the comment's own line when
     * nothing follows.
     */
    public function line(): int
    {
        return $this->module->lineAt($this->documented()->mapOr($this->comment->start, static fn (Node $node): int => $node->start));
    }

    /**
     * The code this comment is about — the first declaration or statement after it — none when nothing follows.
     *
     * @return Option<Node>
     */
    public function documented(): Option
    {
        $after = array_filter($this->module->nodes(), fn (Node $node): bool => $node->start >= $this->comment->end && $node->role !== 'other');

        return Option::fromNullable(array_reduce($after, static fn (?Node $first, Node $node): Node => $first === null || $node->start < $first->start ? $node : $first));
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
