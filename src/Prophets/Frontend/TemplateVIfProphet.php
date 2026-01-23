<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\RepentanceResult;
use JesseGall\CodeCommandments\Support\Pipes\ProphetPipeline;

/**
 * Thou shalt wrap v-if/v-else in template elements.
 *
 * Conditional rendering directives should be on <template> elements,
 * not directly on DOM elements like div, span, etc.
 */
class TemplateVIfProphet extends FrontendCommandment implements SinRepenter
{
    protected array $domElements = [
        'div', 'span', 'p', 'a', 'button', 'input', 'select', 'textarea',
        'ul', 'ol', 'li', 'table', 'tr', 'td', 'th', 'thead', 'tbody',
        'form', 'label', 'img', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'section', 'article', 'aside', 'header', 'footer', 'nav', 'main',
        'figure', 'figcaption', 'blockquote', 'pre', 'code', 'hr', 'br',
    ];

    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function description(): string
    {
        return 'Thou shalt wrap v-if/v-else in template elements';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Conditional rendering directives (v-if, v-else-if, v-else) should be wrapped
in <template> elements rather than applied directly to DOM elements.

This keeps the DOM structure clean and makes conditional blocks explicit.
Templates don't render as actual DOM elements.

Forbidden:
```html
<div v-if="condition">Content</div>
<div v-else-if="other">Other</div>
<div v-else>Fallback</div>
```

Required:
```html
<template v-if="condition">
    <div>Content</div>
</template>
<template v-else-if="other">
    <div>Other</div>
</template>
<template v-else>
    <div>Fallback</div>
</template>
```

Note: This applies to DOM elements, not Vue components.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        if ($this->shouldSkipExtension($filePath)) {
            return $this->righteous();
        }

        $elementsPattern = implode('|', $this->domElements);
        $pattern = '/<('.$elementsPattern.')\s[^>]*v-(if|else-if|else)[=>\s]/i';

        return ProphetPipeline::make($filePath, $content)
            ->extractTemplate()
            ->matchAll($pattern)
            ->mapToSins(function (array $match, ProphetPipeline $pipeline) {
                $element = $match['groups'][1];
                $directive = 'v-'.$match['groups'][2];

                return $pipeline->sinAt(
                    $match['offset'],
                    "{$directive} used directly on <{$element}> instead of <template>",
                    $pipeline->getSnippet($match['offset'], 80),
                    "Wrap the <{$element}> in a <template {$directive}=\"...\"> element"
                );
            })
            ->judge();
    }

    public function canRepent(string $filePath): bool
    {
        return pathinfo($filePath, PATHINFO_EXTENSION) === 'vue';
    }

    public function repent(string $filePath, string $content): RepentanceResult
    {
        $template = $this->extractTemplate($content);

        if ($template === null) {
            return RepentanceResult::unrepentant('No template section found');
        }

        $templateContent = $template['content'];
        $penance = [];
        $maxPasses = 10;
        $pass = 0;

        // Multi-pass to handle nested structures
        while ($pass < $maxPasses) {
            $elementsPattern = implode('|', $this->domElements);
            $pattern = '/<(' . $elementsPattern . ')(\s[^>]*)?\s+(v-(if|else-if|else)(="[^"]*")?)/i';

            if (!preg_match($pattern, $templateContent, $match, PREG_OFFSET_CAPTURE)) {
                break;
            }

            $fullMatch = $match[0][0];
            $position = $match[0][1];
            $element = $match[1][0];
            $attributes = $match[2][0] ?? '';
            $directive = $match[3][0];

            // Find the closing tag
            $closingTag = $this->findClosingTag($templateContent, $element, $position);

            if ($closingTag === null) {
                // Self-closing tag
                $elementEnd = strpos($templateContent, '/>', $position);
                if ($elementEnd === false) {
                    $elementEnd = strpos($templateContent, '>', $position);
                }
                $elementEnd += (substr($templateContent, $elementEnd, 2) === '/>' ? 2 : 1);

                $originalElement = substr($templateContent, $position, $elementEnd - $position);
                // Remove directive from element and wrap
                $cleanElement = preg_replace('/\s+v-(if|else-if|else)(="[^"]*")?/', '', $originalElement);
                $wrapped = "<template {$directive}>\n    {$cleanElement}\n</template>";

                $templateContent = substr($templateContent, 0, $position) . $wrapped . substr($templateContent, $elementEnd);
            } else {
                // Element with closing tag
                $originalBlock = substr($templateContent, $position, $closingTag['end'] - $position);
                // Remove directive from opening tag
                $cleanBlock = preg_replace('/(<' . preg_quote($element, '/') . '(?:\s[^>]*)?)\s+v-(if|else-if|else)(="[^"]*")?/', '$1', $originalBlock);
                $wrapped = "<template {$directive}>\n    {$cleanBlock}\n</template>";

                $templateContent = substr($templateContent, 0, $position) . $wrapped . substr($templateContent, $closingTag['end']);
            }

            $penance[] = "Wrapped <{$element}> with {$directive} in <template>";
            $pass++;
        }

        if (empty($penance)) {
            return RepentanceResult::alreadyRighteous();
        }

        // Rebuild the full file content
        $newContent = substr($content, 0, $template['start']) . $templateContent . substr($content, $template['end']);

        return RepentanceResult::absolved($newContent, $penance);
    }

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
