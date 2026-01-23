<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VuePipeline;

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

        return VuePipeline::make($filePath, $content)
            ->extractTemplate()
            ->returnRighteousIfNoTemplate()
            ->matchAll('/@[a-z:-]+="\\$emit\\([^"]*(\|\||&&|\?)[^"]*\\)"/')
            ->sinsFromMatches(
                'Complex transformation in inline emit handler',
                'Move transformation logic to a handler function in script'
            )
            ->judge();
    }
}
