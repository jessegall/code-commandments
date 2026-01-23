<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Vue;

use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\TextHelper;

/**
 * Context object for Vue SFC analysis pipelines.
 *
 * Provides type-safe access to pipeline data instead of array indexing.
 */
final class VueContext
{
    public function __construct(
        public readonly string $filePath,
        public readonly string $content,
        /** @var array{content: string, start: int, end: int}|null */
        public ?array $template = null,
        /** @var array{content: string, start: int, end: int, lang: string|null, setup: bool}|null */
        public ?array $script = null,
        /** @var array<MatchResult> */
        public array $matches = [],
    ) {}

    /**
     * Create a new context from file path and content.
     */
    public static function from(string $filePath, string $content): self
    {
        return new self($filePath, $content);
    }

    /**
     * Create a copy with updated values.
     */
    public function with(
        ?array $template = null,
        ?array $script = null,
        ?array $matches = null,
    ): self {
        return new self(
            filePath: $this->filePath,
            content: $this->content,
            template: $template ?? $this->template,
            script: $script ?? $this->script,
            matches: $matches ?? $this->matches,
        );
    }

    /**
     * Check if template section was extracted.
     */
    public function hasTemplate(): bool
    {
        return $this->template !== null;
    }

    /**
     * Check if script section was extracted.
     */
    public function hasScript(): bool
    {
        return $this->script !== null;
    }

    /**
     * Check if any matches were found.
     */
    public function hasMatches(): bool
    {
        return ! empty($this->matches);
    }

    /**
     * Get the current section content (template or script).
     */
    public function getSectionContent(): ?string
    {
        return $this->template['content'] ?? $this->script['content'] ?? null;
    }

    /**
     * Get the current section start offset.
     */
    public function getSectionStart(): int
    {
        return $this->template['start'] ?? $this->script['start'] ?? 0;
    }

    /**
     * Get the line number for an offset in the section.
     */
    public function getLineFromOffset(int $offset): int
    {
        $absoluteOffset = $this->getSectionStart() + $offset;

        return TextHelper::getLineNumber($this->content, $absoluteOffset);
    }

    /**
     * Get a snippet of content at an offset.
     */
    public function getSnippet(int $offset, int $length = 60): string
    {
        $sectionContent = $this->getSectionContent();

        if ($sectionContent === null) {
            return '';
        }

        return TextHelper::getSnippet($sectionContent, $offset, $length);
    }

    /**
     * Check if file path contains a string.
     */
    public function filePathContains(string $needle): bool
    {
        return str_contains($this->filePath, $needle);
    }

    /**
     * Check if this is a package file (Prophets/, Commandments/Validators/).
     */
    public function isPackageFile(): bool
    {
        return $this->filePathContains('Commandments/Validators/')
            || $this->filePathContains('Prophets/');
    }

    /**
     * Check if script uses setup syntax.
     */
    public function isSetupScript(): bool
    {
        return $this->script['setup'] ?? false;
    }

    /**
     * Get script language (ts, js, etc.).
     */
    public function getScriptLang(): ?string
    {
        return $this->script['lang'] ?? null;
    }
}
