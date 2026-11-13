<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

use JsonSerializable;

/**
 * One file as the stream's file line writes it, and the writer that read it, which knows what it declared and named.
 */
final readonly class ParsedFile implements JsonSerializable
{
    /**
     * @param  array<string, mixed>  $root
     * @param  list<Comment>  $comments
     */
    public function __construct(
        public string $path,
        public int $errors,
        public array $root,
        public array $comments,
        public TreeWriter $writer,
    ) {}

    public function jsonSerialize(): array
    {
        return ['path' => $this->path, 'language' => 'php', 'errors' => $this->errors, 'root' => $this->root, 'comments' => $this->comments];
    }
}
