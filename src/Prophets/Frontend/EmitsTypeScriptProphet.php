<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

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

        $script = $this->extractScript($content);

        if ($script === null) {
            return $this->skip('No script section found');
        }

        $scriptContent = $script['content'];
        $scriptStart = $script['start'];

        // Look for defineEmits([ - runtime array declaration
        $pattern = '/defineEmits\s*\(\s*\[/';

        if (preg_match($pattern, $scriptContent, $match, PREG_OFFSET_CAPTURE)) {
            $offset = $match[0][1];
            $line = $this->getLineFromOffset($content, $scriptStart + $offset);

            return $this->fallen([
                $this->sinAt(
                    $line,
                    'Runtime array declaration in defineEmits',
                    $this->getSnippet($scriptContent, $offset, 50),
                    'Use TypeScript interface: defineEmits<Emits>()'
                ),
            ]);
        }

        return $this->righteous();
    }
}
