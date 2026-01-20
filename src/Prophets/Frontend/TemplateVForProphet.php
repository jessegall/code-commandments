<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\RepentanceResult;

/**
 * Thou shalt use <template v-for> wrapper instead of v-for on elements.
 *
 * Always wrap v-for loops in a <template> element to keep DOM structure clean.
 */
class TemplateVForProphet extends FrontendCommandment implements SinRepenter
{
    /**
     * DOM elements that should not have v-for directly on them.
     */
    protected array $domElements = [
        'div', 'span', 'li', 'tr', 'td', 'button', 'a', 'p',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    ];

    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function description(): string
    {
        return 'Use <template v-for> wrapper instead of v-for on elements';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Always wrap v-for loops in a <template> element.

This keeps the DOM structure clean and makes it clear where the loop
starts and ends. Place :key on the template element.

Sinful:
```html
<div v-for="item in items" :key="item.id">
    {{ item.name }}
</div>
```

Righteous:
```html
<template v-for="item in items" :key="item.id">
    <div>{{ item.name }}</div>
</template>
```
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        if ($this->shouldSkipExtension($filePath)) {
            return $this->righteous();
        }

        $template = $this->extractTemplate($content);

        if ($template === null) {
            return $this->skip('No template section found');
        }

        $templateContent = $template['content'];
        $templateStart = $template['start'];
        $sins = [];

        // Build pattern for DOM elements with v-for directly on them
        $elementsPattern = implode('|', $this->domElements);
        $pattern = '/<(' . $elementsPattern . ')(\s[^>]*)?\s+v-for\s*=/i';

        preg_match_all($pattern, $templateContent, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $match) {
            $element = $match[0];
            $position = $match[1];
            $tagName = $matches[1][array_search($match, $matches[0])][0];

            $line = $this->getLineFromOffset($content, $templateStart + $position);

            $sins[] = $this->sinAt(
                $line,
                "v-for on <{$tagName}> element should be wrapped in <template>",
                trim($element),
                "Wrap the <{$tagName}> in a <template v-for=\"...\"> element"
            );
        }

        return empty($sins) ? $this->righteous() : $this->fallen($sins);
    }

    public function canRepent(string $filePath): bool
    {
        return pathinfo($filePath, PATHINFO_EXTENSION) === 'vue';
    }

    public function repent(string $filePath, string $content): RepentanceResult
    {
        if ($this->shouldSkipExtension($filePath)) {
            return RepentanceResult::unchanged();
        }

        $template = $this->extractTemplate($content);

        if ($template === null) {
            return RepentanceResult::unchanged();
        }

        $templateContent = $template['content'];
        $penance = [];
        $maxPasses = 10;
        $pass = 0;

        // Build pattern for DOM elements with v-for
        $elementsPattern = implode('|', $this->domElements);

        // Multi-pass to handle nested structures
        while ($pass < $maxPasses) {
            // Pattern to find opening tag with v-for
            $pattern = '/<(' . $elementsPattern . ')(\s[^>]*?v-for\s*=\s*"([^"]*)"[^>]*)(\s*\/?>)/is';

            if (!preg_match($pattern, $templateContent, $match, PREG_OFFSET_CAPTURE)) {
                break;
            }

            $position = $match[0][1];
            $fullOpenTag = $match[0][0];
            $tagName = $match[1][0];
            $attributes = $match[2][0];
            $vForValue = $match[3][0];
            $tagEnding = $match[4][0];
            $isSelfClosing = str_ends_with(trim($tagEnding), '/>');

            // Extract :key from attributes
            $keyAttr = '';
            if (preg_match('/\s+:key\s*=\s*"([^"]*)"/', $attributes, $keyMatch)) {
                $keyAttr = ' :key="' . $keyMatch[1] . '"';
                $attributes = preg_replace('/\s+:key\s*=\s*"[^"]*"/', '', $attributes);
            }

            // Remove v-for from attributes
            $attributes = preg_replace('/\s+v-for\s*=\s*"[^"]*"/', '', $attributes);

            if ($isSelfClosing) {
                // Self-closing tag - simple replacement
                $cleanElement = '<' . $tagName . $attributes . ' />';
                $wrapped = '<template v-for="' . $vForValue . '"' . $keyAttr . '>' . $cleanElement . '</template>';

                $templateContent = substr($templateContent, 0, $position) .
                    $wrapped .
                    substr($templateContent, $position + strlen($fullOpenTag));
            } else {
                // Find the matching closing tag
                $closingTag = $this->findClosingTag($templateContent, $tagName, $position);

                if ($closingTag === null) {
                    // Can't find closing tag, skip this one
                    $pass++;
                    continue;
                }

                // Extract the full element including content
                $elementEnd = $closingTag['end'];
                $openTagEnd = $position + strlen($fullOpenTag);
                $innerContent = substr($templateContent, $openTagEnd, $closingTag['start'] - $openTagEnd);

                // Build the wrapped element
                $cleanElement = '<' . $tagName . $attributes . '>' . $innerContent . '</' . $tagName . '>';
                $wrapped = '<template v-for="' . $vForValue . '"' . $keyAttr . '>' . $cleanElement . '</template>';

                $templateContent = substr($templateContent, 0, $position) .
                    $wrapped .
                    substr($templateContent, $elementEnd);
            }

            $penance[] = "Wrapped <{$tagName}> with v-for in <template>";
            $pass++;
        }

        if (empty($penance)) {
            return RepentanceResult::unchanged();
        }

        // Replace the template content in the original file
        $newContent = substr($content, 0, $template['start']) .
            $templateContent .
            substr($content, $template['end']);

        return RepentanceResult::absolved($newContent, $penance);
    }

    /**
     * Find the matching closing tag for an element, properly handling nested tags.
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

            if ($nextOpen < $nextClose && !str_ends_with(trim($openMatch[0][0]), '/>')) {
                $depth++;
                $pos = $nextOpen + strlen($openMatch[0][0]);
            } else {
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
