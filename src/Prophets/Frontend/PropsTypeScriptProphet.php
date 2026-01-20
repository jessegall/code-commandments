<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

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

        $script = $this->extractScript($content);

        if ($script === null) {
            return $this->skip('No script section found');
        }

        $scriptContent = $script['content'];
        $scriptStart = $script['start'];

        // Look for defineProps({ - runtime object declaration
        $pattern = '/defineProps\s*\(\s*\{/';

        if (preg_match($pattern, $scriptContent, $match, PREG_OFFSET_CAPTURE)) {
            $offset = $match[0][1];
            $line = $this->getLineFromOffset($content, $scriptStart + $offset);

            return $this->fallen([
                $this->sinAt(
                    $line,
                    'Runtime object declaration in defineProps',
                    $this->getSnippet($scriptContent, $offset, 50),
                    'Use TypeScript interface: defineProps<Props>()'
                ),
            ]);
        }

        return $this->righteous();
    }
}
