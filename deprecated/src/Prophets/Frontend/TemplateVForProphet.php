<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\RepentanceResult;
use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VuePipeline;
use JesseGall\CodeCommandments\Support\Traits\TemplateElementHelper;

/**
 * Thou shalt use <template v-for> wrapper instead of v-for on elements.
 *
 * Always wrap v-for loops in a <template> element to keep DOM structure clean.
 */
class TemplateVForProphet extends FrontendCommandment implements SinRepenter
{
    use TemplateElementHelper;

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

        $elementsPattern = implode('|', $this->domElements);
        $pattern = '/<(' . $elementsPattern . ')(\s[^>]*)?\s+v-for\s*=/i';

        return VuePipeline::make($filePath, $content)
            ->inTemplate()
            ->matchAll($pattern)
            ->forEachMatch(function (MatchResult $match, VuePipeline $pipeline) {
                // Extract the tag name from the match
                if (preg_match('/<(\w+)/', $match->match, $tagMatch)) {
                    $tagName = $tagMatch[1];

                    return $pipeline->sinAt(
                        $match->offset,
                        "v-for on <{$tagName}> element should be wrapped in <template>",
                        trim($match->match),
                        "Wrap the <{$tagName}> in a <template v-for=\"...\"> element"
                    );
                }

                return null;
            })
            ->judge();
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

        $pipeline = VuePipeline::make($filePath, $content)->extractTemplate();

        if ($pipeline->shouldSkip() || $pipeline->getSectionContent() === null) {
            return RepentanceResult::unchanged();
        }

        $templateContent = $pipeline->getSectionContent();
        $penance = [];
        $maxPasses = 10;
        $pass = 0;

        // Build pattern for DOM elements with v-for
        $elementsPattern = implode('|', $this->domElements);

        // One indentation level for this file, used to nest the unwrapped
        // element under its new <template>.
        $indentUnit = $this->detectIndentUnit($templateContent);

        // Multi-pass to handle nested structures
        while ($pass < $maxPasses) {
            // Pattern to find opening tag with v-for
            // Lazy tail so a trailing `/` is left for group 4 — otherwise
            // `[^>]*` swallows it and self-closing tags look like open tags.
            $pattern = '/<(' . $elementsPattern . ')(\s[^>]*?v-for\s*=\s*"([^"]*)"[^>]*?)(\s*\/?>)/is';

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

            // Indentation of the line the element sits on — the <template> takes
            // its place, and the element nests one level deeper.
            $baseIndent = $this->leadingIndentAt($templateContent, $position);

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
                $wrapped = '<template v-for="' . $vForValue . '"' . $keyAttr . '>' . "\n"
                    . $baseIndent . $indentUnit . $cleanElement . "\n"
                    . $baseIndent . '</template>';

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

                // Build the wrapped element, nesting the original element (and
                // all of its inner content) one indentation level deeper.
                $cleanElement = '<' . $tagName . $attributes . '>' . $innerContent . '</' . $tagName . '>';
                $nested = $this->indentInnerBlock($baseIndent . $cleanElement, $indentUnit);
                $wrapped = '<template v-for="' . $vForValue . '"' . $keyAttr . '>' . "\n"
                    . $nested . "\n"
                    . $baseIndent . '</template>';

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
        $ctx = $pipeline->getContext();
        $newContent = substr($content, 0, $ctx->template['start']) .
            $templateContent .
            substr($content, $ctx->template['end']);

        return RepentanceResult::absolved($newContent, $penance);
    }

    /**
     * The leading whitespace of the line `$position` sits on, or '' when the
     * element is not at the start of its line (nothing clean to indent from).
     */
    private function leadingIndentAt(string $content, int $position): string
    {
        $newlinePos = strrpos(substr($content, 0, $position), "\n");
        $lineStart = $newlinePos === false ? 0 : $newlinePos + 1;
        $prefix = substr($content, $lineStart, $position - $lineStart);

        return preg_match('/^[ \t]*$/', $prefix) === 1 ? $prefix : '';
    }

    /**
     * One indentation level for this file: tabs if any line is tab-indented,
     * otherwise the smallest run of leading spaces seen. Defaults to 4 spaces.
     */
    private function detectIndentUnit(string $content): string
    {
        if (preg_match('/\n\t/', $content) === 1) {
            return "\t";
        }

        $smallest = null;

        if (preg_match_all('/\n( +)\S/', $content, $matches) > 0) {
            foreach ($matches[1] as $spaces) {
                $length = strlen($spaces);

                if ($smallest === null || $length < $smallest) {
                    $smallest = $length;
                }
            }
        }

        return str_repeat(' ', $smallest ?? 4);
    }

    /**
     * Prepend `$prefix` to every non-blank line of `$block`, shifting the whole
     * element right by one level without leaving trailing whitespace on blanks.
     */
    private function indentInnerBlock(string $block, string $prefix): string
    {
        $lines = explode("\n", $block);

        foreach ($lines as $index => $line) {
            if (trim($line) !== '') {
                $lines[$index] = $prefix . $line;
            }
        }

        return implode("\n", $lines);
    }
}
