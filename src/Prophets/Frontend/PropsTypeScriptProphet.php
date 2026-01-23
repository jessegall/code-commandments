<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VuePipeline;

/**
 * Use TypeScript interface for defineProps instead of runtime declaration.
 *
 * Always use TypeScript generic syntax for defineProps instead of runtime object declaration.
 */
class PropsTypeScriptProphet extends FrontendCommandment
{
    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function description(): string
    {
        return 'Use TypeScript interface for defineProps instead of runtime declaration';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Always use TypeScript generic syntax for defineProps instead of runtime object declaration.

Bad:
    const props = defineProps({
        title: String,
        count: { type: Number, default: 0 }
    })

Good:
    interface Props {
        title: string;
        count?: number;
    }
    const props = withDefaults(defineProps<Props>(), {
        count: 0
    })
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        if ($this->shouldSkipExtension($filePath)) {
            return $this->righteous();
        }

        return VuePipeline::make($filePath, $content)
            ->inScript()
            ->matchAll('/defineProps\s*\(\s*\{/')
            ->sinsFromMatches(
                'Runtime object declaration in defineProps',
                'Use TypeScript interface: defineProps<Props>()'
            )
            ->judge();
    }
}
