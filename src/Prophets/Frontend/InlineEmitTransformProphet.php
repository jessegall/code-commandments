<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

/**
 * Avoid inline emit handlers with transformation logic in templates.
 *
 * Don't use complex transformations in inline emit handlers.
 * Simple forwarding is OK, but logic should be in a handler function.
 */
class InlineEmitTransformProphet extends FrontendCommandment
{
    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function description(): string
    {
        return 'Avoid inline emit handlers with transformation logic in templates';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Don't use complex transformations in inline emit handlers.
Simple forwarding is OK, but logic should be in a handler function.

Bad:
    @update:model-value="$emit('update:value', $event || null)"
    @change="$emit('change', items.filter(i => i.active))"

Good:
    @update:model-value="handleUpdate"
    @change="handleChange"

    // In script:
    function handleUpdate(value: string | null) {
        emit('update:value', value || null)
    }
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

        // Look for $emit with || or && or ? (ternary) in template bindings
        $pattern = '/@[a-z:-]+="\\$emit\\([^"]*(\|\||&&|\?)[^"]*\\)"/';

        if (preg_match($pattern, $templateContent, $match, PREG_OFFSET_CAPTURE)) {
            $offset = $match[0][1];
            $line = $this->getLineFromOffset($content, $templateStart + $offset);

            return $this->fallen([
                $this->sinAt(
                    $line,
                    'Complex transformation in inline emit handler',
                    $this->getSnippet($templateContent, $offset, 60),
                    'Move transformation logic to a handler function in script'
                ),
            ]);
        }

        return $this->righteous();
    }
}
