<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes;

use Closure;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Sin;
use JesseGall\CodeCommandments\Support\Pipeline;
use JesseGall\CodeCommandments\Support\RegexMatcher;

/**
 * A specialized pipeline for building prophets declaratively.
 *
 * Example usage:
 *
 * return ProphetPipeline::make($filePath, $content)
 *     ->extractTemplate()
 *     ->matchAll('/pattern/')
 *     ->reject(fn($match) => $this->isAllowed($match))
 *     ->mapToSins(fn($match) => $this->createSin($match))
 *     ->judge();
 */
final class ProphetPipeline
{
    private string $filePath;

    private string $content;

    private ?array $section = null;

    private array $matches = [];

    private array $sins = [];

    private ?string $skipReason = null;

    private function __construct(string $filePath, string $content)
    {
        $this->filePath = $filePath;
        $this->content = $content;
    }

    /**
     * Start a new prophet pipeline.
     */
    public static function make(string $filePath, string $content): self
    {
        return new self($filePath, $content);
    }

    /**
     * Extract the template section from a Vue SFC.
     */
    public function extractTemplate(): self
    {
        $this->section = $this->extractVueSection('template');

        if ($this->section === null) {
            $this->skipReason = 'No template section found';
        }

        return $this;
    }

    /**
     * Extract the script section from a Vue SFC.
     */
    public function extractScript(): self
    {
        $this->section = $this->extractVueSection('script');

        if ($this->section === null) {
            $this->skipReason = 'No script section found';
        }

        return $this;
    }

    /**
     * Match all occurrences of a pattern in the current section.
     */
    public function matchAll(string $pattern): self
    {
        if ($this->shouldSkip()) {
            return $this;
        }

        $this->matches = RegexMatcher::for($this->section['content'])
            ->matchAll($pattern);

        return $this;
    }

    /**
     * Filter matches using a callback.
     */
    public function filter(Closure $callback): self
    {
        if ($this->shouldSkip()) {
            return $this;
        }

        $this->matches = Pipeline::from($this->matches)
            ->filter($callback)
            ->values()
            ->toArray();

        return $this;
    }

    /**
     * Reject matches using a callback (inverse of filter).
     */
    public function reject(Closure $callback): self
    {
        if ($this->shouldSkip()) {
            return $this;
        }

        $this->matches = Pipeline::from($this->matches)
            ->reject($callback)
            ->values()
            ->toArray();

        return $this;
    }

    /**
     * Transform matches using a callback.
     */
    public function map(Closure $callback): self
    {
        if ($this->shouldSkip()) {
            return $this;
        }

        $this->matches = Pipeline::from($this->matches)
            ->map($callback)
            ->toArray();

        return $this;
    }

    /**
     * Flat map matches using a callback.
     */
    public function flatMap(Closure $callback): self
    {
        if ($this->shouldSkip()) {
            return $this;
        }

        $this->matches = Pipeline::from($this->matches)
            ->flatMap($callback)
            ->toArray();

        return $this;
    }

    /**
     * Execute a callback for each match.
     */
    public function each(Closure $callback): self
    {
        if ($this->shouldSkip()) {
            return $this;
        }

        foreach ($this->matches as $index => $match) {
            $callback($match, $index);
        }

        return $this;
    }

    /**
     * Map matches to sins using a callback.
     * The callback receives the match, the pipeline (for context), and index.
     * Should return a Sin or null.
     *
     * @param  Closure(array, self, int): ?Sin  $callback
     */
    public function mapToSins(Closure $callback): self
    {
        if ($this->shouldSkip()) {
            return $this;
        }

        $this->sins = Pipeline::from($this->matches)
            ->map(fn ($match, $index) => $callback($match, $this, $index))
            ->filter(fn ($sin) => $sin instanceof Sin)
            ->values()
            ->toArray();

        return $this;
    }

    /**
     * Add sins directly.
     *
     * @param  array<Sin>  $sins
     */
    public function addSins(array $sins): self
    {
        $this->sins = array_merge($this->sins, $sins);

        return $this;
    }

    /**
     * Get the line number for an offset in the original content.
     */
    public function getLineFromOffset(int $offset): int
    {
        $absoluteOffset = ($this->section['start'] ?? 0) + $offset;

        return substr_count(substr($this->content, 0, $absoluteOffset), "\n") + 1;
    }

    /**
     * Create a sin at a specific offset in the section.
     */
    public function sinAt(int $offset, string $message, ?string $snippet = null, ?string $suggestion = null): Sin
    {
        return Sin::at($this->getLineFromOffset($offset), $message, $snippet, $suggestion);
    }

    /**
     * Get a snippet of the section content at an offset.
     */
    public function getSnippet(int $offset, int $length = 60): string
    {
        if ($this->section === null) {
            return '';
        }

        $start = max(0, $offset - 20);
        $snippet = substr($this->section['content'], $start, $length);
        $snippet = trim(preg_replace('/\s+/', ' ', $snippet) ?? $snippet);

        if ($start > 0) {
            $snippet = '...'.$snippet;
        }

        if ($offset + 40 < strlen($this->section['content'])) {
            $snippet .= '...';
        }

        return $snippet;
    }

    /**
     * Get the section content.
     */
    public function getSectionContent(): ?string
    {
        return $this->section['content'] ?? null;
    }

    /**
     * Get the section start offset.
     */
    public function getSectionStart(): int
    {
        return $this->section['start'] ?? 0;
    }

    /**
     * Get the current matches.
     *
     * @return array<array{match: string, offset: int, groups: array<string>}>
     */
    public function getMatches(): array
    {
        return $this->matches;
    }

    /**
     * Get the original content.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Get the file path.
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * Check if pipeline should skip processing.
     */
    public function shouldSkip(): bool
    {
        return $this->skipReason !== null;
    }

    /**
     * Set a skip reason.
     */
    public function skip(string $reason): self
    {
        $this->skipReason = $reason;

        return $this;
    }

    /**
     * Conditionally execute a callback.
     */
    public function when(bool $condition, Closure $callback): self
    {
        if ($condition && ! $this->shouldSkip()) {
            $callback($this);
        }

        return $this;
    }

    /**
     * Conditionally skip.
     */
    public function skipWhen(bool $condition, string $reason): self
    {
        if ($condition) {
            $this->skipReason = $reason;
        }

        return $this;
    }

    /**
     * Build and return the final Judgment.
     */
    public function judge(): Judgment
    {
        if ($this->skipReason !== null) {
            return Judgment::skipped($this->skipReason);
        }

        return empty($this->sins)
            ? Judgment::righteous()
            : Judgment::fallen($this->sins);
    }

    /**
     * Get sins array (useful for custom judgment building).
     *
     * @return array<Sin>
     */
    public function getSins(): array
    {
        return $this->sins;
    }

    /**
     * Extract a Vue SFC section.
     *
     * @return array{content: string, start: int, end: int}|null
     */
    private function extractVueSection(string $type): ?array
    {
        if ($type === 'template') {
            return $this->extractTemplateSection();
        }

        if ($type === 'script') {
            return $this->extractScriptSection();
        }

        return null;
    }

    /**
     * Extract the template section with proper nesting handling.
     *
     * @return array{content: string, start: int, end: int}|null
     */
    private function extractTemplateSection(): ?array
    {
        if (! preg_match('/<template(\s+[^>]*)?>/', $this->content, $openMatch, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $openTagStart = $openMatch[0][1];
        $openTagEnd = $openTagStart + strlen($openMatch[0][0]);
        $contentStart = $openTagEnd;

        $depth = 1;
        $pos = $openTagEnd;
        $length = strlen($this->content);

        while ($depth > 0 && $pos < $length) {
            $nextOpen = preg_match('/<template(\s+[^>]*)?>/', $this->content, $m, PREG_OFFSET_CAPTURE, $pos) ? $m[0][1] : PHP_INT_MAX;
            $nextClose = preg_match('/<\/template\s*>/', $this->content, $m, PREG_OFFSET_CAPTURE, $pos) ? $m[0][1] : PHP_INT_MAX;

            if ($nextClose === PHP_INT_MAX) {
                return null;
            }

            if ($nextOpen < $nextClose) {
                $depth++;
                preg_match('/<template(\s+[^>]*)?>/', $this->content, $m, PREG_OFFSET_CAPTURE, $pos);
                $pos = $m[0][1] + strlen($m[0][0]);
            } else {
                $depth--;
                preg_match('/<\/template\s*>/', $this->content, $m, PREG_OFFSET_CAPTURE, $pos);
                if ($depth === 0) {
                    return [
                        'content' => substr($this->content, $contentStart, $m[0][1] - $contentStart),
                        'start' => $contentStart,
                        'end' => $m[0][1],
                    ];
                }
                $pos = $m[0][1] + strlen($m[0][0]);
            }
        }

        return null;
    }

    /**
     * Extract the script section.
     *
     * @return array{content: string, start: int, end: int, lang: string|null, setup: bool}|null
     */
    private function extractScriptSection(): ?array
    {
        if (! preg_match('/<script(\s+[^>]*)?>(.+?)<\/script>/s', $this->content, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $attributes = $matches[1][0] ?? '';
        $scriptContent = $matches[2][0];
        $start = $matches[2][1];

        $lang = null;
        if (preg_match('/lang=["\'](\w+)["\']/', $attributes, $langMatch)) {
            $lang = $langMatch[1];
        }

        return [
            'content' => $scriptContent,
            'start' => $start,
            'end' => $start + strlen($scriptContent),
            'lang' => $lang,
            'setup' => str_contains($attributes, 'setup'),
        ];
    }
}
