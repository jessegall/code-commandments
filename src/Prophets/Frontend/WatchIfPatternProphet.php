<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VueContext;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VuePipeline;

/**
 * Use whenever() instead of watch() with if condition.
 *
 * When you have a watch that only executes when a condition is truthy,
 * use the `whenever` composable from VueUse instead.
 */
class WatchIfPatternProphet extends FrontendCommandment
{
    public function requiresConfession(): bool
    {
        return true;
    }

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

        return VuePipeline::make($filePath, $content)
            ->inScript()
            ->matchAll('/watch\s*\([^)]+,\s*\([^)]*\)\s*=>\s*\{[^}]*\n\s*if\s*\(/s')
            ->mapToWarnings(fn (VueContext $ctx) => array_map(
                fn ($match) => Warning::at(
                    $match->line,
                    'Found watch with if condition - consider using whenever()',
                    'Use whenever() from VueUse for cleaner, more declarative code'
                ),
                $ctx->matches
            ))
            ->judge();
    }
}
