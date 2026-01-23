<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commandments;

use JesseGall\CodeCommandments\Contracts\Commandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Sin;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Support\TextHelper;

/**
 * Base class for all commandments (prophets).
 * Provides common functionality for judging files.
 */
abstract class BaseCommandment implements Commandment
{
    /**
     * Configuration options for this commandment.
     *
     * @var array<string, mixed>
     */
    protected array $config = [];

    /**
     * Set configuration options.
     *
     * @param array<string, mixed> $config
     */
    public function configure(array $config): static
    {
        $this->config = array_merge($this->config, $config);

        return $this;
    }

    /**
     * Get a configuration value.
     */
    protected function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * By default, commandments do not require manual confession.
     */
    public function requiresConfession(): bool
    {
        return false;
    }

    /**
     * Create a righteous judgment.
     */
    protected function righteous(): Judgment
    {
        return Judgment::righteous();
    }

    /**
     * Create a fallen judgment with sins.
     *
     * @param array<Sin> $sins
     */
    protected function fallen(array $sins): Judgment
    {
        return Judgment::fallen($sins);
    }

    /**
     * Create a sin at a specific line.
     */
    protected function sinAt(int $line, string $message, ?string $snippet = null, ?string $suggestion = null): Sin
    {
        return Sin::at($line, $message, $snippet, $suggestion);
    }

    /**
     * Create a general sin without line number.
     */
    protected function sin(string $message, ?string $suggestion = null): Sin
    {
        return Sin::general($message, $suggestion);
    }

    /**
     * Create a warning at a specific line.
     */
    protected function warningAt(int $line, string $message, ?string $snippet = null): Warning
    {
        return Warning::at($line, $message, $snippet);
    }

    /**
     * Create a general warning.
     */
    protected function warning(string $message): Warning
    {
        return Warning::general($message);
    }

    /**
     * Skip this file with a reason.
     */
    protected function skip(string $reason): Judgment
    {
        return Judgment::skipped($reason);
    }

    /**
     * Check if file should be skipped based on extension.
     */
    protected function shouldSkipExtension(string $filePath): bool
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        return !in_array($extension, $this->applicableExtensions(), true);
    }

    /**
     * Get the line number for a position in content.
     */
    protected function getLineNumber(string $content, int $position): int
    {
        return TextHelper::getLineNumber($content, $position);
    }

    /**
     * Get a snippet of code around a position.
     */
    protected function getSnippet(string $content, int $position, int $length = 60): string
    {
        return TextHelper::getSnippet($content, $position, $length);
    }

    /**
     * Find all matches of a pattern in content.
     *
     * @return array<array{0: string, 1: int}> Array of [match, position] pairs
     */
    protected function findMatches(string $pattern, string $content): array
    {
        preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);

        return $matches[0] ?? [];
    }
}
