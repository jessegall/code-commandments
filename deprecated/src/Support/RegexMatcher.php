<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * Fluent regex matching utility.
 */
final class RegexMatcher
{
    private string $content;

    private function __construct(string $content)
    {
        $this->content = $content;
    }

    /**
     * Create a new matcher for the given content.
     */
    public static function for(string $content): self
    {
        return new self($content);
    }

    /**
     * Find all matches for a pattern.
     *
     * @return array<array{match: string, offset: int, groups: array<string>}>
     */
    public function matchAll(string $pattern): array
    {
        preg_match_all($pattern, $this->content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        return Pipeline::from($matches)
            ->map(function (array $match) {
                $groups = [];
                foreach ($match as $i => $group) {
                    if ($i > 0) {
                        $groups[$i] = $group[0];
                    }
                }

                return [
                    'match' => $match[0][0],
                    'offset' => $match[0][1],
                    'groups' => $groups,
                ];
            })
            ->toArray();
    }

    /**
     * Find first match for a pattern.
     *
     * @return array{match: string, offset: int, groups: array<string>}|null
     */
    public function matchFirst(string $pattern): ?array
    {
        $matches = $this->matchAll($pattern);

        return $matches[0] ?? null;
    }

    /**
     * Check if pattern matches.
     */
    public function matches(string $pattern): bool
    {
        return (bool) preg_match($pattern, $this->content);
    }

    /**
     * Check if any of the patterns match.
     *
     * @param  array<string>  $patterns
     */
    public function matchesAny(array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($this->matches($pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if all patterns match.
     *
     * @param  array<string>  $patterns
     */
    public function matchesAll(array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (! $this->matches($pattern)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Replace matches with a callback.
     */
    public function replaceWith(string $pattern, callable $callback): string
    {
        return preg_replace_callback($pattern, $callback, $this->content) ?? $this->content;
    }

    /**
     * Replace matches with a string.
     */
    public function replace(string $pattern, string $replacement): string
    {
        return preg_replace($pattern, $replacement, $this->content) ?? $this->content;
    }

    /**
     * Get the content.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Get line number from character offset.
     */
    public function getLineFromOffset(int $offset): int
    {
        return TextHelper::getLineNumber($this->content, $offset);
    }

    /**
     * Get a snippet around an offset.
     */
    public function getSnippet(int $offset, int $length = 60): string
    {
        return TextHelper::getSnippet($this->content, $offset, $length);
    }
}
