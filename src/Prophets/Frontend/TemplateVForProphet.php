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
        $originalTemplateContent = $templateContent;
        $absolved = 0;

        // Build pattern for DOM elements with v-for
        $elementsPattern = implode('|', $this->domElements);

        // Pattern to match full elements with v-for (including self-closing and normal tags)
        // We need to handle both self-closing tags and tags with content
        $selfClosingPattern = '/<(' . $elementsPattern . ')(\s[^>]*?\s+v-for\s*=\s*"[^"]*"[^>]*)\s*\/>/is';
        $openTagPattern = '/<(' . $elementsPattern . ')(\s[^>]*?\s+v-for\s*=\s*"[^"]*"[^>]*)>/is';

        // Process self-closing tags first
        $templateContent = preg_replace_callback(
            $selfClosingPattern,
            function ($matches) use (&$absolved) {
                $tagName = $matches[1];
                $attributes = $matches[2];

                // Extract v-for and :key from attributes
                if (preg_match('/\s+v-for\s*=\s*"([^"]*)"/', $attributes, $vForMatch)) {
                    $vForValue = $vForMatch[1];
                    $attributes = preg_replace('/\s+v-for\s*=\s*"[^"]*"/', '', $attributes);

                    $keyAttr = '';
                    if (preg_match('/\s+:key\s*=\s*"([^"]*)"/', $attributes, $keyMatch)) {
                        $keyAttr = ' :key="' . $keyMatch[1] . '"';
                        $attributes = preg_replace('/\s+:key\s*=\s*"[^"]*"/', '', $attributes);
                    }

                    $absolved++;

                    return '<template v-for="' . $vForValue . '"' . $keyAttr . '>' .
                        '<' . $tagName . $attributes . ' />' .
                        '</template>';
                }

                return $matches[0];
            },
            $templateContent
        );

        // Process tags with content (more complex - need to find matching close tag)
        // This is a simplified version that handles single-level tags
        $pattern = '/<(' . $elementsPattern . ')(\s[^>]*?\s+v-for\s*=\s*"([^"]*)"[^>]*)>(.*?)<\/\1>/is';

        $templateContent = preg_replace_callback(
            $pattern,
            function ($matches) use (&$absolved) {
                $tagName = $matches[1];
                $attributes = $matches[2];
                $vForValue = $matches[3];
                $innerContent = $matches[4];

                // Remove v-for from attributes
                $attributes = preg_replace('/\s+v-for\s*=\s*"[^"]*"/', '', $attributes);

                $keyAttr = '';
                if (preg_match('/\s+:key\s*=\s*"([^"]*)"/', $attributes, $keyMatch)) {
                    $keyAttr = ' :key="' . $keyMatch[1] . '"';
                    $attributes = preg_replace('/\s+:key\s*=\s*"[^"]*"/', '', $attributes);
                }

                $absolved++;

                return '<template v-for="' . $vForValue . '"' . $keyAttr . '>' .
                    '<' . $tagName . $attributes . '>' . $innerContent . '</' . $tagName . '>' .
                    '</template>';
            },
            $templateContent
        );

        if ($templateContent === $originalTemplateContent) {
            return RepentanceResult::unchanged();
        }

        // Replace the template content in the original file
        $newContent = substr($content, 0, $template['start']) .
            $templateContent .
            substr($content, $template['end']);

        return RepentanceResult::absolved(
            $newContent,
            "{$absolved} v-for directive(s) wrapped in <template>"
        );
    }
}
