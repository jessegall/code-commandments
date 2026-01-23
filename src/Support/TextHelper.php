<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * Helper methods for text/content analysis.
 */
final class TextHelper
{
    /**
     * Get line number from character offset in content.
     */
    public static function getLineNumber(string $content, int $offset): int
    {
        return substr_count(substr($content, 0, $offset), "\n") + 1;
    }

    /**
     * Get a snippet of content around an offset.
     */
    public static function getSnippet(string $content, int $offset, int $length = 60): string
    {
        $start = max(0, $offset - 20);
        $snippet = substr($content, $start, $length);
        $snippet = trim(preg_replace('/\s+/', ' ', $snippet) ?? $snippet);

        if ($start > 0) {
            $snippet = '...' . $snippet;
        }

        if ($offset + 40 < strlen($content)) {
            $snippet .= '...';
        }

        return $snippet;
    }
}
