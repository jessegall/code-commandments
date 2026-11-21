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
     */
    public function __construct(
        public string $path,
        public int $errors,
        public ?string $module,
        public array $root,
        public array $comments,
        public Translator $translator,
    ) {}

    public function jsonSerialize(): array
    {
        return array_filter([
            'path' => $this->path, 'language' => 'php', 'errors' => $this->errors, 'module' => $this->module,
            'resolver' => ['tool' => 'php-parser', 'ran' => true], 'root' => $this->root, 'comments' => $this->comments,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
