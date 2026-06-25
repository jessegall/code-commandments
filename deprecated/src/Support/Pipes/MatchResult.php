<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes;

/**
 * Represents a regex match result with context information.
 */
readonly class MatchResult
{
    /**
     * @param string $name Pattern identifier
     * @param string $pattern The regex pattern used
     * @param string $match The full matched string
     * @param int $line Line number where match was found
     * @param int|null $offset Character offset in content (null for line-based matching)
     * @param string|null $content Snippet of content around the match
     * @param array<int|string, string> $groups Captured groups from the regex
     */
    public function __construct(
        public string $name,
        public string $pattern,
        public string $match,
        public int $line,
        public ?int $offset,
        public ?string $content,
        public array $groups,
    ) {}

    /**
     * Create a Match from an array (for backwards compatibility).
     *
     * @param array{name: string, pattern: string, match: string, line: int, offset?: int|null, content?: string|null, groups: array} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            pattern: $data['pattern'],
            match: $data['match'],
            line: $data['line'],
            offset: $data['offset'] ?? null,
            content: $data['content'] ?? null,
            groups: $data['groups'],
        );
    }

    /**
     * Convert to array (for backwards compatibility).
     *
     * @return array{name: string, pattern: string, match: string, line: int, offset: int|null, content: string|null, groups: array}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'pattern' => $this->pattern,
            'match' => $this->match,
            'line' => $this->line,
            'offset' => $this->offset,
            'content' => $this->content,
            'groups' => $this->groups,
        ];
    }
}
