<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

use JsonSerializable;

/**
 * One file as the stream's file line writes it, and the translator that read it, which knows what it declared and named.
 */
final readonly class ParsedFile implements JsonSerializable
{
    /**
     * @param  array<string, mixed>  $root
     * @param  list<array<string, mixed>>  $comments
     * @param  list<array<string, mixed>>  $types  the file's type table, each resolvedType an index into it
     * @param  list<string>  $reads  the other files whose declarations typed this one
     */
    public function __construct(
        public string $path,
        public int $errors,
        public ?string $module,
        public array $root,
        public array $comments,
        public Translator $translator,
        public array $types = [],
        public array $reads = [],
        public ?string $publishedBy = null,
    ) {}

    public function jsonSerialize(): array
    {
        return array_filter([
            'path' => $this->path, 'language' => 'php', 'errors' => $this->errors, 'module' => $this->module, 'publishedBy' => $this->publishedBy,
            'resolver' => ['tool' => 'php-parser', 'ran' => true], 'reads' => $this->reads === [] ? null : $this->reads,
            'root' => $this->root, 'comments' => $this->comments, 'types' => $this->types === [] ? null : $this->types,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
