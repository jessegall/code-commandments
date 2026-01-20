<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

/**
 * Use whenever() instead of watch() with if condition.
 *
 * When you have a watch that only executes when a condition is truthy,
 * use the `whenever` composable from VueUse instead.
 */
class WatchIfPatternProphet extends FrontendCommandment
{
    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function description(): string
    {
        return 'Use whenever() instead of watch() with if condition';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
When you have a watch that only executes when a condition is truthy,
use the `whenever` composable from VueUse instead.

Bad:
    watch(isReady, (value) => {
        if (value) {
            doSomething()
        }
    })

Good:
    whenever(isReady, () => {
        doSomething()
    })

The `whenever` composable is cleaner and more declarative.
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

        // Look for watch( followed by if ( within a few lines
        $pattern = '/watch\s*\([^)]+,\s*\([^)]*\)\s*=>\s*\{[^}]*\n\s*if\s*\(/s';

        if (preg_match($pattern, $scriptContent, $match, PREG_OFFSET_CAPTURE)) {
            $offset = $match[0][1];
            $line = $this->getLineFromOffset($content, $scriptStart + $offset);

            return Judgment::withWarnings([
                $this->warningAt(
                    $line,
                    'Found watch with if condition - consider using whenever()',
                    'Use whenever() from VueUse for cleaner, more declarative code'
                ),
            ]);
        }

        return $this->righteous();
    }
}
