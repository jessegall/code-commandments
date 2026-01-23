<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Traits;

/**
 * Helper trait for working with template elements.
 */
trait TemplateElementHelper
{
    /**
     * Common DOM elements.
     *
     * @var array<string>
     */
    protected static array $commonDomElements = [
        'div', 'span', 'p', 'a', 'button', 'input', 'select', 'textarea',
        'ul', 'ol', 'li', 'table', 'tr', 'td', 'th', 'thead', 'tbody',
        'form', 'label', 'img', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'section', 'article', 'aside', 'header', 'footer', 'nav', 'main',
        'figure', 'figcaption', 'blockquote', 'pre', 'code', 'hr', 'br',
    ];

    /**
     * Find the matching closing tag for an element, properly handling nested tags.
     *
     * @return array{start: int, end: int}|null
     */
    protected function findClosingTag(string $content, string $tag, int $startPos): ?array
    {
        $openTagPattern = '/<' . preg_quote($tag, '/') . '(?:\s[^>]*)?>|<' . preg_quote($tag, '/') . '(?:\s[^>]*)?\s*\/>/i';
        $closeTagPattern = '/<\/' . preg_quote($tag, '/') . '\s*>/i';

        $depth = 1;
        $pos = $startPos;

        // Move past the opening tag
        if (preg_match('/<' . preg_quote($tag, '/') . '(?:\s[^>]*)?\/?>/i', $content, $match, PREG_OFFSET_CAPTURE, $pos)) {
            // Check if self-closing
            if (str_ends_with(trim($match[0][0]), '/>')) {
                return null; // Self-closing
            }
            $pos = $match[0][1] + strlen($match[0][0]);
        }

        while ($depth > 0 && $pos < strlen($content)) {
            $nextOpen = preg_match($openTagPattern, $content, $openMatch, PREG_OFFSET_CAPTURE, $pos) ? $openMatch[0][1] : PHP_INT_MAX;
            $nextClose = preg_match($closeTagPattern, $content, $closeMatch, PREG_OFFSET_CAPTURE, $pos) ? $closeMatch[0][1] : PHP_INT_MAX;

            if ($nextClose === PHP_INT_MAX) {
                return null; // No closing tag found
            }

            if ($nextOpen < $nextClose) {
                // Found an open tag before the next close tag
                $isSelfClosing = str_ends_with(trim($openMatch[0][0]), '/>');
                if (!$isSelfClosing) {
                    // Normal open tag - increment depth
                    $depth++;
                }
                // For self-closing tags, just skip them (don't change depth)
                $pos = $nextOpen + strlen($openMatch[0][0]);
            } else {
                // Found a close tag
                $depth--;
                if ($depth === 0) {
                    return [
                        'start' => $closeMatch[0][1],
                        'end' => $closeMatch[0][1] + strlen($closeMatch[0][0]),
                    ];
                }
                $pos = $nextClose + strlen($closeMatch[0][0]);
            }
        }

        return null;
    }
}
