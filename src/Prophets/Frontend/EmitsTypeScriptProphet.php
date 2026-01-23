<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VuePipeline;

/**
 * Use TypeScript interface for defineEmits instead of runtime declaration.
 *
 * Always use TypeScript generic syntax for defineEmits instead of runtime array declaration.
 */
class EmitsTypeScriptProphet extends FrontendCommandment
{
    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function description(): string
    {
        return 'Use TypeScript interface for defineEmits instead of runtime declaration';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Always use TypeScript generic syntax for defineEmits instead of runtime array declaration.

Bad:
    const emit = defineEmits(['update:modelValue', 'submit'])

Good:
    interface Emits {
        (e: 'update:modelValue', value: string): void;
        (e: 'submit'): void;
    }
    const emit = defineEmits<Emits>()
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        if ($this->shouldSkipExtension($filePath)) {
            return $this->righteous();
        }

        return VuePipeline::make($filePath, $content)
            ->extractScript()
            ->returnRighteousIfNoScript()
            ->matchAll('/defineEmits\s*\(\s*\[/')
            ->sinsFromMatches(
                'Runtime array declaration in defineEmits',
                'Use TypeScript interface: defineEmits<Emits>()'
            )
            ->judge();
    }
}
